<?php

namespace App\Services;

use App\Mail\PlainMail;
use App\Models\CaseRecord;
use App\Models\PortalMessage;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Portert fra base44/shared/portalThread.ts (+ inviteCode.ts og rand() fra oidcProvider.ts)
 *
 * Delte hjelpere for Min side-meldingstråder (henvendelser fra nettsidene).
 * Brukes av: ReceiveChatMessage/mottaHenvendelse (innkommende), ReplyToPortalCase (ansatt-svar),
 * PostPortalReply (gjest-svar), GetMyPortalData (lesing), GrantMinSideAccess.
 *
 * SCOPING: PortalMessage leses av gjesten kun via GetMyPortalData filtrert på
 * actor.email — e-post er identitet; treff bare på telefon er IKKE identitet.
 *
 * Avvik fra originalen:
 *  - E-post til ansatte gikk via Outlook-connector (Graph, fra post@aprop.no). Her brukes
 *    Laravel Mail (PlainMail) med reply-to; avsender settes av mail.from i config.
 *  - base44.users.inviteUser() (Base44-plattformens registreringsmail) har ingen ekvivalent
 *    og er utelatt; brukeren opprettes via UserInvite/BankID-flyten.
 *  - Deno.env OIDC_ISSUER → config('services.portal.issuer'), VARSEL_MOTTAKERE → config('services.portal.varsel_mottakere').
 */
class PortalThread
{
    public const POSTMOTTAK = 'post@aprop.no';
    public const AVSENDER_NAVN = 'Appendix Properties';
    private const VARSEL_MOTTAKERE_DEFAULT = 'svein.arthur.jensen@appendixholding.no:90620833;charlotte.herland@spsh.no:48249335';
    private const INVITE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function issuer(): string
    {
        return rtrim((string) (config('services.portal.issuer') ?: 'https://aprop.no'), '/');
    }

    /** @return array<int, array{email: string, mobile: string}> */
    public static function parseVarselMottakere(): array
    {
        $raw = trim((string) (config('services.portal.varsel_mottakere') ?: self::VARSEL_MOTTAKERE_DEFAULT));
        $out = [];
        foreach (array_filter(array_map('trim', explode(';', $raw))) as $entry) {
            $parts = array_map('trim', explode(':', $entry));
            $email = strtolower($parts[0] ?? '');
            if ($email !== '') {
                $out[] = ['email' => $email, 'mobile' => $parts[1] ?? ''];
            }
        }
        return $out;
    }

    public static function escapeHtml(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Sveve SMS direkte (ingen admin-krav). true ved minst én OK-melding. */
    public static function sendSveve(?string $to, string $msg, string $from = 'Appendix'): bool
    {
        if (!$to) {
            return false;
        }
        $r = app(SveveSms::class)->send($to, $msg, $from);
        return (bool) ($r['ok'] ?? false);
    }

    /** Base64url-tilfeldig streng (rand() fra oidcProvider.ts). */
    public static function rand(int $len = 16): string
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
    }

    /** Kort invitasjonskode uten tvetydige tegn (inviteCode.ts). */
    public static function randomCode(int $len = 6): string
    {
        $s = '';
        $n = strlen(self::INVITE_ALPHABET);
        for ($i = 0; $i < $len; $i++) {
            $s .= self::INVITE_ALPHABET[random_int(0, 255) % $n];
        }
        return $s;
    }

    public static function generateUniqueInviteCode(int $len = 6): string
    {
        for ($i = 0; $i < 12; $i++) {
            $code = self::randomCode($len);
            try {
                if (!UserInvite::where('short_code', $code)->exists()) {
                    return $code;
                }
            } catch (\Throwable) {
                return $code;
            }
        }
        return self::randomCode($len);
    }

    /** Opprett en melding i tråden. */
    public static function postPortalMessage(array $opts): PortalMessage
    {
        return PortalMessage::create([
            'case_id' => $opts['case_id'],
            'case_number' => $opts['case_number'] ?? '',
            'subject' => $opts['subject'] ?? '',
            'user_email' => strtolower((string) $opts['user_email']),
            'user_id' => $opts['user_id'] ?? '',
            'direction' => $opts['direction'],
            'message' => $opts['message'],
            'channel' => $opts['channel'],
            'sender_name' => $opts['sender_name'] ?? '',
            'status' => 'ulest',
        ]);
    }

    /**
     * Auto-opprett gjest-Min side. Finner bruker på e-post (identitet); finnes hun ikke,
     * opprettes UserInvite (role 'guest', status 'sent') og BankID-lenke sendes på SMS + e-post.
     * @return array{user_id: string, identified: bool, invite_sent: bool, error: string}
     */
    public static function autoProvisionGuestFromEnquiry(string $name, string $email, string $mobile): array
    {
        $email = strtolower($email);
        $result = ['user_id' => '', 'identified' => false, 'invite_sent' => false, 'error' => ''];

        // 1) Eksisterende bruker på e-post.
        try {
            $u = User::where('email', $email)->first();
            if ($u) {
                $result['user_id'] = $u->id;
                $result['identified'] = true;
                if ($mobile && !$u->mobile) {
                    try { $u->update(['mobile' => $mobile]); } catch (\Throwable) {}
                }
                return $result;
            }
        } catch (\Throwable $e) {
            $result['error'] = Str::limit($e->getMessage(), 200, '');
        }

        // 2) Mangler mobil → kan ikke sende SMS-invitasjon.
        if (!$mobile) {
            $result['error'] = 'mangler mobil — kan ikke sende invitasjon';
            return $result;
        }

        // 3) Ventende invitasjon for e-posten?
        $inv = null;
        try {
            $inv = UserInvite::where('email', $email)->get()->first(function ($i) {
                if (!in_array($i->status, ['draft', 'sent'], true)) {
                    return false;
                }
                return !($i->expires_at && now()->greaterThan(\Carbon\Carbon::parse($i->expires_at)));
            });
        } catch (\Throwable) {}

        // 4) Ny UserInvite (guest, auto-godkjent).
        if (!$inv) {
            try {
                $shortCode = self::generateUniqueInviteCode();
                $inv = UserInvite::create([
                    'token' => self::rand(24),
                    'short_code' => $shortCode,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $mobile,
                    'role' => 'guest',
                    'status' => 'sent',
                    'expires_at' => now()->addDays(7)->toIso8601String(),
                    'created_at' => now()->toIso8601String(),
                    'sms_body' => 'Vi har mottatt henvendelsen din. Svaret kommer på Min side – logg inn med BankID her: ' . self::issuer() . '/i/' . $shortCode,
                    'send_sms' => true,
                    'approved_at' => now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                $result['error'] = 'kunne ikke opprette invitasjon: ' . Str::limit($e->getMessage(), 200, '');
                return $result;
            }
        }

        // 5) base44.users.inviteUser() — ingen ekvivalent (se klassekommentar).

        // 6) Finn/oppdater User (rolle guest + mobil) dersom den finnes nå.
        try {
            $u = User::where('email', $email)->first();
            if ($u) {
                $result['user_id'] = $u->id;
                $result['identified'] = true;
                $patch = ['role' => 'guest'];
                if ($mobile && !$u->mobile) {
                    $patch['mobile'] = $mobile;
                }
                try { $u->update($patch); } catch (\Throwable) {}
            }
        } catch (\Throwable) {}

        // 7) SMS med BankID-lenke.
        $link = self::issuer() . '/i/' . $inv->short_code;
        $smsText = 'Vi har mottatt henvendelsen din. Svaret kommer på Min side – logg inn med BankID her: ' . $link;
        $result['invite_sent'] = self::sendSveve($mobile, $smsText);

        // 8) E-post (best-effort).
        try {
            $hei = $name ? ' ' . self::escapeHtml($name) : '';
            $html = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1e293b;max-width:600px;margin:0 auto;padding:20px">'
                . "<p>Hei{$hei},</p>"
                . '<p>Vi har mottatt henvendelsen din. Svaret kommer på Min side – logg inn med BankID her:</p>'
                . '<p style="margin:24px 0;text-align:center"><a href="' . $link . '" style="background:#1e3a5f;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;font-weight:bold">Logg inn med BankID</a></p>'
                . '<p style="font-size:13px;color:#64748b">Lenken er personlig. Hvis du ikke forventet denne meldingen, kan du se bort fra den.</p>'
                . '<p style="font-size:12px;color:#94a3b8">Appendix Properties — aprop.no</p>'
                . '</body></html>';
            Mail::to($email)->send(new PlainMail('Vi har mottatt henvendelsen din — Appendix Properties', $html));
        } catch (\Throwable) {}

        return $result;
    }

    /** Varsel til ansatte ved ny henvendelse (e-post + SMS med kort oppsummering og lenke). */
    public static function sendStaffEnquiryNotification(CaseRecord $sak, array $felt): void
    {
        $mottakere = self::parseVarselMottakere();
        $caseLink = self::issuer() . '/CaseDetail?id=' . rawurlencode($sak->id);
        $smsText = "Ny henvendelse: {$felt['emne']} fra {$felt['navn']} — sak {$sak->case_number}. Se saken: {$caseLink}";

        $e = fn ($v) => self::escapeHtml($v);
        $html = '<p>Ny henvendelse fra kontaktskjemaet på ' . $e($felt['kilde'] ?? '') . ' – sak ' . $e($sak->case_number) . '.</p>'
            . '<table cellpadding="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px">'
            . '<tr><td style="color:#64748b;padding:6px 10px 6px 0">Navn</td><td style="padding:6px 0"><strong>' . $e($felt['navn'] ?? '') . '</strong></td></tr>'
            . '<tr><td style="color:#64748b;padding:6px 10px 6px 0">E-post</td><td style="padding:6px 0"><a href="mailto:' . $e($felt['epost'] ?? '') . '">' . $e($felt['epost'] ?? '') . '</a></td></tr>'
            . '<tr><td style="color:#64748b;padding:6px 10px 6px 0">Telefon</td><td style="padding:6px 0">' . ($e($felt['telefon'] ?? '') ?: '—') . '</td></tr>'
            . '<tr><td style="color:#64748b;padding:6px 10px 6px 0">Gjelder</td><td style="padding:6px 0">' . $e($felt['emne'] ?? '') . '</td></tr>'
            . '</table>'
            . '<p style="margin-top:16px"><a href="' . $caseLink . '">Åpne saken i Saksbehandling</a></p>';
        $subject = "Ny henvendelse: {$felt['emne']} — {$felt['navn']} ({$sak->case_number})";

        foreach ($mottakere as $m) {
            try {
                Mail::to($m['email'])->send(new PlainMail($subject, $html, $felt['epost'] ?? null));
            } catch (\Throwable $ex) {
                Log::error('portalThread: staff email feilet ' . Str::limit($ex->getMessage(), 300, ''));
            }
        }
        foreach ($mottakere as $m) {
            if ($m['mobile']) {
                self::sendSveve($m['mobile'], $smsText);
            }
        }
    }

    /** Generelt driftsvarsel til VARSEL_MOTTAKERE (e-post + SMS). */
    public static function sendStaffAlert(string $subject, string $html, string $sms): void
    {
        $mottakere = self::parseVarselMottakere();
        foreach ($mottakere as $m) {
            try {
                Mail::to($m['email'])->send(new PlainMail($subject, $html));
            } catch (\Throwable $ex) {
                Log::error('sendStaffAlert: e-post feilet ' . Str::limit($ex->getMessage(), 300, ''));
            }
        }
        foreach ($mottakere as $m) {
            if ($m['mobile']) {
                self::sendSveve($m['mobile'], $sms);
            }
        }
    }

    /** Varsel til gjest når ansatt har svart — aldri selve innholdet. */
    public static function sendCounterpartReplyNotification(string $toEmail, ?string $toMobile = null): void
    {
        $link = self::issuer() . '/minside';
        $smsText = 'Du har fått svar fra Appendix Properties på Min side. Logg inn med BankID her: ' . $link;
        if ($toMobile) {
            self::sendSveve($toMobile, $smsText);
        }
        try {
            $html = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1e293b;max-width:600px;margin:0 auto;padding:20px">'
                . '<p>Hei,</p>'
                . '<p>Du har fått svar fra Appendix Properties på Min side. Logg inn med BankID for å lese meldingen:</p>'
                . '<p style="margin:24px 0;text-align:center"><a href="' . $link . '" style="background:#1e3a5f;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;font-weight:bold">Logg inn på Min side</a></p>'
                . '<p style="font-size:12px;color:#94a3b8">Appendix Properties — aprop.no</p>'
                . '</body></html>';
            Mail::to($toEmail)->send(new PlainMail('Du har fått svar fra Appendix Properties på Min side', $html));
        } catch (\Throwable) {}
    }

    /** Enkel kvittering på e-post ved ny henvendelse (ingen Min side-lenke). */
    public static function sendEnquiryReceipt(string $navn, string $epost): void
    {
        try {
            $hei = $navn ? ' ' . self::escapeHtml($navn) : '';
            $html = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1e293b;max-width:600px;margin:0 auto;padding:20px">'
                . "<p>Hei{$hei},</p>"
                . '<p>Takk for henvendelsen. Den er mottatt, og vi tar kontakt så snart vi kan – normalt innen to virkedager.</p>'
                . '<p style="margin-top:24px">Vennlig hilsen,<br><strong>Appendix Properties</strong><br>aprop.no</p>'
                . '<p style="font-size:12px;color:#94a3b8">Dette er en automatisk kvittering — du trenger ikke svare på denne e-posten.</p>'
                . '</body></html>';
            Mail::to($epost)->send(new PlainMail('Takk for henvendelsen — Appendix Properties', $html));
        } catch (\Throwable $e) {
            Log::error('sendEnquiryReceipt feilet ' . Str::limit($e->getMessage(), 300, ''));
        }
    }

    /**
     * Hovedflyt for ny henvendelse: sak FØRST, så kvittering + tråd + varsler.
     * $opts: navn, epost, telefon, emne, melding, kilde, fra?, til?, gjester?, property_id?, property_name?, ip?
     * @return array{sak: CaseRecord, identified: bool, invite_sent: bool, obs: bool}
     */
    public static function handleNewEnquiry(array $opts): array
    {
        $sak = self::createEnquiryCase($opts);
        return self::processEnquiry($sak, $opts);
    }

    public static function createEnquiryCase(array $opts): CaseRecord
    {
        $fra = $opts['fra'] ?? '';
        $til = $opts['til'] ?? '';
        // Som originalen: tomme linjer (inkl. den tomme skillelinjen) filtreres bort.
        $linjer = array_values(array_filter([
            $opts['melding'] ?? '',
            '',
            'Telefon: ' . (($opts['telefon'] ?? '') ?: '—'),
            ($fra || $til) ? 'Ønsket periode: ' . ($fra ?: '?') . ' – ' . ($til ?: '?') : '',
            !empty($opts['gjester']) ? 'Antall gjester: ' . $opts['gjester'] : '',
            'Kilde: ' . ($opts['kilde'] ?? ''),
        ], fn ($l) => $l !== ''));

        return CaseRecord::create([
            'title' => ($opts['emne'] ?? '') . ' – ' . ($opts['navn'] ?? ''),
            'case_number' => 'SAK-' . substr((string) (int) (microtime(true) * 1000), -6),
            'case_type' => 'henvendelse',
            'status' => 'ny',
            'priority' => 'normal',
            'description' => implode("\n", $linjer),
            'counterpart_name' => $opts['navn'] ?? '',
            'counterpart_email' => $opts['epost'] ?? '',
            'property_id' => $opts['property_id'] ?? null,
            'property_name' => $opts['property_name'] ?? null,
            'tags' => array_merge(['nettside', $opts['kilde'] ?? ''], !empty($opts['ip']) ? ['ip:' . $opts['ip']] : []),
        ]);
    }

    public static function processEnquiry(CaseRecord $sak, array $opts): array
    {
        $out = ['sak' => $sak, 'identified' => false, 'invite_sent' => false, 'obs' => false];

        try {
            self::sendEnquiryReceipt($opts['navn'] ?? '', $opts['epost'] ?? '');
        } catch (\Throwable $e) {
            Log::error('portalThread: kvittering feilet ' . Str::limit($e->getMessage(), 300, ''));
        }

        try {
            self::postPortalMessage([
                'case_id' => $sak->id,
                'case_number' => $sak->case_number,
                'subject' => $sak->title,
                'user_email' => $opts['epost'] ?? '',
                'direction' => 'innkommende',
                'message' => $opts['melding'] ?? '',
                'channel' => 'nettside',
                'sender_name' => $opts['navn'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('portalThread: meldingstråd feilet ' . Str::limit($e->getMessage(), 300, ''));
        }

        try {
            $sak->update(['tags' => array_merge($sak->tags ?? [], ['behandlet'])]);
        } catch (\Throwable) {}

        try {
            self::sendStaffEnquiryNotification($sak, [
                'navn' => $opts['navn'] ?? '', 'emne' => $opts['emne'] ?? '', 'epost' => $opts['epost'] ?? '',
                'telefon' => $opts['telefon'] ?? '', 'kilde' => $opts['kilde'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('portalThread: varsel feilet ' . Str::limit($e->getMessage(), 300, ''));
        }

        return $out;
    }
}
