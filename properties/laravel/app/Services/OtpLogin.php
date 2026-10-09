<?php

namespace App\Services;

use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Engangskode (SMS/e-post) som innlogging på BEGRENSET nivå (amr=otp). Aldri admin/ansatte, aldri BankID-status.
 * Prinsipper (rapport 9.10.2026 om feil i Base44-versjonene):
 *  - kryptografisk tilfeldig kode (random_int), 6 siffer, hashet med HMAC (APP_KEY) – aldri klartekst
 *  - gyldig 10 min, maks 5 gale forsøk per kode, ny kode ugyldiggjør forrige
 *  - grenser per mottaker (3 / 10 min) og per IP (10 / 10 min), uavhengig av om mottaker finnes
 *  - NØYTRALT svar uansett om mottaker finnes (ingen kontoorakel); SMS/e-post sendes bare til kjent, tillatt bruker
 *  - ingen auto-opprettelse av bruker, ingen «dry_run»/kode i svaret
 */
class OtpLogin
{
    public const TTL_SECONDS = 600;
    public const MAX_ATTEMPTS = 5;
    public const PER_TARGET = 3;
    public const PER_IP = 10;
    public const WINDOW_SECONDS = 600;

    public function __construct(private readonly SveveSms $sms, private readonly MicrosoftGraph $graph)
    {
    }

    /** Roller som kan bruke kode-innlogging (ansatte/admin er alltid utelukket). */
    public static function allowedRoles(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) env('OTP_ALLOWED_ROLES', 'pasient,medlem,leietaker,guest')))));
    }

    public static function normalize(string $channel, string $target): ?string
    {
        $target = trim($target);
        if ($channel === 'email') {
            $e = strtolower($target);
            return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
        }
        $d = preg_replace('/[^\d+]/', '', $target);
        $d = preg_replace('/^(\+|00)47/', '', $d);
        return preg_match('/^[2-9]\d{7}$/', $d) ? $d : null; // norsk 8-sifret nummer
    }

    private function mac(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function findUser(string $channel, string $target): ?User
    {
        $q = User::query();
        if ($channel === 'email') {
            $q->whereRaw('lower(email) = ?', [$target]);
        } else {
            $q->where(function ($w) use ($target) {
                $w->where('mobile', $target)->orWhere('mobile', '+47' . $target)->orWhere('mobile', '47' . $target);
            });
        }
        $users = $q->limit(2)->get();
        if ($users->count() !== 1) {
            return null; // ukjent eller tvetydig → ingen kode
        }
        $u = $users->first();
        if ($u->isAdmin() || !in_array((string) $u->role, self::allowedRoles(), true)) {
            return null;
        }
        if ($u->access_until && $u->access_until->endOfDay()->isPast()) {
            return null;
        }
        return $u;
    }

    /** @return bool alltid true utad (nøytralt); false bare ved ugyldig input eller overskredet grense */
    public function request(string $channel, string $rawTarget, ?string $ip): array
    {
        $target = self::normalize($channel, $rawTarget);
        if (!$target) {
            return ['ok' => false, 'status' => 422, 'message' => $channel === 'email' ? 'Ugyldig e-postadresse' : 'Ugyldig mobilnummer'];
        }
        $tHash = $this->mac($channel . ':' . $target);
        $ipKey = 'otp:ip:' . ($ip ?: '-');
        $tKey = 'otp:t:' . $tHash;
        if (Cache::get($ipKey, 0) >= self::PER_IP || Cache::get($tKey, 0) >= self::PER_TARGET) {
            return ['ok' => false, 'status' => 429, 'message' => 'For mange forsøk. Vent noen minutter og prøv igjen.'];
        }
        foreach ([$ipKey, $tKey] as $k) {
            Cache::add($k, 0, self::WINDOW_SECONDS);
            Cache::increment($k);
        }

        $user = $this->findUser($channel, $target);
        $masked = $channel === 'email' ? preg_replace('/^(.).*(@.*)$/', '$1•••$2', $target) : '••• •• ' . substr($target, -3);

        // Ugyldiggjør tidligere koder for mottakeren
        OtpChallenge::where('target_hash', $tHash)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = null;
        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }
        $ch = OtpChallenge::create([
            'channel' => $channel, 'target_hash' => $tHash, 'target_masked' => $masked,
            'user_id' => $user?->id, 'code_hash' => $code ? $this->mac($tHash . ':' . $code) : null,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS), 'ip' => $ip, 'created_at' => now(),
        ]);

        if ($user && $code) {
            try {
                $this->deliver($channel, $target, $code);
            } catch (\Throwable $e) {
                Log::warning('otp: utsendelse feilet (' . $channel . '): ' . $e->getMessage());
                $ch->update(['consumed_at' => now()]);
            }
        }
        // Samme svar enten mottaker finnes eller ikke
        return ['ok' => true, 'status' => 200, 'message' => 'Hvis mottakeren er registrert, er en kode på vei.', 'expires_in' => self::TTL_SECONDS];
    }

    private function deliver(string $channel, string $target, string $code): void
    {
        $text = "Din engangskode for Appendix er $code. Den gjelder i 10 minutter. Ikke del den med andre.";
        if ($channel === 'sms') {
            $r = $this->sms->send($target, $text);
            if (!($r['ok'] ?? false)) {
                throw new \RuntimeException($r['error'] ?? 'Sveve-feil');
            }
            return;
        }
        $from = (string) env('OTP_MAIL_FROM', 'post@aprop.no');
        $this->graph->post("/users/{$from}/sendMail", ['message' => [
            'subject' => 'Din engangskode',
            'body' => ['contentType' => 'Text', 'content' => $text],
            'toRecipients' => [['emailAddress' => ['address' => $target]]],
        ], 'saveToSentItems' => false]);
    }

    /** @return User|null bruker ved riktig kode, ellers null */
    public function verify(string $channel, string $rawTarget, string $code, ?string $ip): ?User
    {
        $target = self::normalize($channel, $rawTarget);
        if (!$target || !preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $tHash = $this->mac($channel . ':' . $target);
        $vKey = 'otp:v:' . $tHash;
        if (Cache::get($vKey, 0) >= self::MAX_ATTEMPTS * 3) { // hard grense på tvers av koder
            return null;
        }
        Cache::add($vKey, 0, self::WINDOW_SECONDS);

        $ch = OtpChallenge::where('target_hash', $tHash)->whereNull('consumed_at')->where('expires_at', '>', now())
            ->orderByDesc('created_at')->first();
        if (!$ch || !$ch->code_hash || !$ch->user_id) {
            Cache::increment($vKey);
            return null;
        }
        if ($ch->attempts >= self::MAX_ATTEMPTS) {
            $ch->update(['consumed_at' => now()]);
            return null;
        }
        if (!hash_equals($ch->code_hash, $this->mac($tHash . ':' . $code))) {
            $ch->increment('attempts');
            Cache::increment($vKey);
            return null;
        }
        $ch->update(['consumed_at' => now()]);
        $user = User::find($ch->user_id);
        // Revurder tillatelse på verifiseringstidspunktet
        return ($user && !$user->isAdmin() && in_array((string) $user->role, self::allowedRoles(), true)
            && !($user->access_until && $user->access_until->endOfDay()->isPast())) ? $user : null;
    }
}
