<?php

namespace App\Functions;

use App\Jobs\BehandleHenvendelse as BehandleHenvendelseJobb;
use App\Models\CaseRecord;
use App\Models\MarketingContact;
use App\Models\Property;
use App\Models\User;
use App\Services\PortalThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Portert fra base44/functions/mottaHenvendelse/entry.ts
 *
 * OFFENTLIG endepunkt (ingen innlogging): kontaktskjemaene på aprop.no og geilolodge.com
 * (WordPress på egen server) lander her – POST /api/functions/mottaHenvendelse (+ OPTIONS for CORS).
 *
 * Rekkefølge (krav fra Svein 04.10.2026): lagre sak/forespørsel FØRST, så Min side, så varsler.
 * Feil i Min side eller varsel skal aldri gi feil tilbake til nettsiden.
 *
 * Hver henvendelse (både «Booking Geilo Lodge» og «leieforesporsel») blir:
 *   1. en sak (Case, case_type «henvendelse») i Saksbehandling          – synkront her
 *   2. en meldingstråd på avsenderens Min side (PortalMessage)           – i jobben BehandleHenvendelse
 *   3. enkel kvittering på e-post til avsender                           – i jobben
 *   4. varsel til oss (VARSEL_MOTTAKERE) på e-post + SMS                 – i jobben
 *
 * Åpent endepunkt med lagvis, beskjeden beskyttelse (som før):
 *   1. honeypot-felt («nettsted») mennesker ikke ser
 *   2. sperregrense per e-postadresse og per IP-adresse, siste time
 *   3. tak på lengden av hvert felt
 *   (+ throttle:30,1 på ruten)
 * Svaret er det samme uansett om vi lagret eller stoppet noe som så ut som søppel.
 *
 * Avvik: bakgrunnskallet (HTTP til behandleHenvendelse med 2,5 s frist) er erstattet av en køjobb.
 * Kan jobben ikke legges i køen (Redis nede), kjøres behandlingen direkte – som originalens fallback.
 * IP-sperren teller saker med tag «ip:<ip>» siste time direkte i databasen (originalen så på de 50 siste).
 */
class MottaHenvendelse extends Base44Function
{
    private const TILLATTE_OPPHAV = [
        'https://aprop.no',
        'https://www.aprop.no',
        'https://portal.aprop.no',
        'https://geilolodge.com',
        'https://www.geilolodge.com',
        // Testadresser på egen server før DNS flyttes
        'https://aprop.test.appendixholding.no',
        'https://geilolodge.test.appendixholding.no',
    ];

    private const EMNER = ['Booking Geilo Lodge', 'Leie bolig', 'Leie kontor', 'Parkering', 'Eier/utleie', 'Annet'];

    private const MAKS_PER_EPOST_PER_TIME = 3;
    private const MAKS_PER_IP_PER_TIME = 10;

    public function __invoke(?User $user, array $payload): array
    {
        $engelsk = $this->tekst($payload['sprak'] ?? null, 5) === 'en';

        // Honeypot — mennesker ser ikke feltet; bot fyller det og får kvittering.
        if ($this->tekst($payload['nettsted'] ?? null, 200) !== '') {
            return $this->kvittering($engelsk);
        }

        $ip = $this->klientIp($payload);

        // --- «Send en forespørsel» på forsiden av aprop.no (type «leieforesporsel») ---
        // Lagres som MarketingContact (lead/interesselisten) OG som en sak med Min side-tråd.
        if ($this->tekst($payload['type'] ?? null, 30) === 'leieforesporsel') {
            return $this->leieforesporsel($payload, $ip);
        }

        // --- Kontaktskjema (navn/emne/melding) — geilolodge.com og aprop.no ---
        $navn = $this->tekst($payload['navn'] ?? null, 120);
        $epost = strtolower($this->tekst($payload['epost'] ?? null, 160));
        $telefon = $this->tekst($payload['telefon'] ?? null, 40);
        $melding = $this->tekst($payload['melding'] ?? null, 5000);
        $kilde = $this->tekst($payload['kilde'] ?? null, 120) ?: 'aprop.no';
        $emneInn = $this->tekst($payload['emne'] ?? null, 60);
        $emne = in_array($emneInn, self::EMNER, true) ? $emneInn : (preg_match('/geilo/i', $kilde) ? 'Booking Geilo Lodge' : 'Annet');
        $fra = $this->erDato($this->tekst($payload['fra'] ?? null, 10)) ? $this->tekst($payload['fra'], 10) : '';
        $til = $this->erDato($this->tekst($payload['til'] ?? null, 10)) ? $this->tekst($payload['til'], 10) : '';
        $gjester = preg_replace('/[^0-9]/', '', $this->tekst($payload['gjester'] ?? null, 10));

        if (mb_strlen($navn) < 2) {
            throw new FunctionException($engelsk ? 'Please enter your name.' : 'Skriv inn navnet ditt.', 400);
        }
        if (!$this->erEpost($epost)) {
            throw new FunctionException($engelsk ? 'Please enter a valid email address.' : 'Skriv inn en gyldig e-postadresse.', 400);
        }
        if (mb_strlen($melding) < 5 && $fra === '') {
            throw new FunctionException($engelsk ? 'Please write a few words about your enquiry.' : 'Skriv noen ord om hva henvendelsen gjelder.', 400);
        }

        // Sperregrenser per e-post og per IP, siste time.
        $enTimeSiden = now()->subHour();
        try {
            $fraSammeEpost = CaseRecord::where('case_type', 'henvendelse')
                ->where('counterpart_email', $epost)
                ->where('created_date', '>', $enTimeSiden)
                ->count();
        } catch (\Throwable) {
            $fraSammeEpost = 0;
        }
        if ($fraSammeEpost >= self::MAKS_PER_EPOST_PER_TIME) {
            return $this->kvittering($engelsk);
        }
        if ($ip !== '') {
            try {
                $fraSammeIp = CaseRecord::where('case_type', 'henvendelse')
                    ->where('created_date', '>', $enTimeSiden)
                    ->whereJsonContains('tags', 'ip:' . $ip)
                    ->count();
            } catch (\Throwable) {
                $fraSammeIp = 0;
            }
            if ($fraSammeIp >= self::MAKS_PER_IP_PER_TIME) {
                return $this->kvittering($engelsk);
            }
        }

        // Knytt til eiendommen når henvendelsen gjelder Geilo Lodge.
        $propertyId = null;
        $propertyName = null;
        if ($emne === 'Booking Geilo Lodge') {
            try {
                $geilo = Property::get()->first(fn ($p) => preg_match('/geilo\s*lodge/i', (string) $p->name) === 1);
                if ($geilo) {
                    $propertyId = $geilo->id;
                    $propertyName = $geilo->name;
                }
            } catch (\Throwable) {
            }
        }

        // Sak + Min side-tråd + kvittering + varsel (feil her velter ikke nettsiden).
        try {
            $this->lagreOgStart([
                'navn' => $navn, 'epost' => $epost, 'telefon' => $telefon, 'emne' => $emne, 'melding' => $melding,
                'kilde' => $kilde, 'fra' => $fra, 'til' => $til, 'gjester' => $gjester,
                'property_id' => $propertyId, 'property_name' => $propertyName, 'ip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::error('mottaHenvendelse: handleNewEnquiry feilet ' . Str::limit($e->getMessage(), 300, ''));
        }

        return $this->kvittering($engelsk);
    }

    /** «Send en forespørsel» (interesseliste) – samme svar som originalen: {success, melding}. */
    private function leieforesporsel(array $payload, string $ip): array
    {
        $fornavn = $this->tekst($payload['fornavn'] ?? null, 80);
        $etternavn = $this->tekst($payload['etternavn'] ?? null, 80);
        $ep = strtolower($this->tekst($payload['epost'] ?? null, 160));
        $telefon = $this->tekst($payload['telefon'] ?? null, 40);
        $interesseInn = $this->tekst($payload['interesse'] ?? null, 20);
        $interesse = in_array($interesseInn, ['langtid', 'korttid', 'parkering', 'annet'], true) ? $interesseInn : 'annet';

        if (mb_strlen($fornavn) < 1 || !$this->erEpost($ep)) {
            throw new FunctionException('Vennligst fyll inn navn og e-post.', 400);
        }
        if (($payload['samtykke'] ?? null) !== true) {
            throw new FunctionException('Du må samtykke til at vi kan kontakte deg.', 400);
        }

        // Sperregrense per e-post (MarketingContact) siste time.
        try {
            $tidligere = MarketingContact::where('email', $ep)->where('created_date', '>', now()->subHour())->count();
        } catch (\Throwable) {
            $tidligere = 0;
        }
        if ($tidligere >= self::MAKS_PER_EPOST_PER_TIME) {
            return ['success' => true, 'melding' => 'Takk for forespørselen!'];
        }

        $navn = trim($fornavn . ' ' . $etternavn);
        $eiendom = $this->tekst($payload['eiendom'] ?? null, 160);
        $melding = $this->tekst($payload['melding'] ?? null, 5000);
        $kilde = $this->tekst($payload['kilde'] ?? null, 120) ?: 'aprop.no';
        $emneMap = ['langtid' => 'Leie bolig', 'korttid' => 'Leie bolig', 'parkering' => 'Parkering', 'annet' => 'Annet'];
        $emne = $emneMap[$interesse] ?? 'Leie bolig';

        // 1) MarketingContact (lead) — beholdes som før.
        MarketingContact::create([
            'type' => 'lead',
            'first_name' => $fornavn,
            'last_name' => $etternavn,
            'email' => $ep,
            'phone' => $telefon,
            'source' => 'website',
            'consent_marketing' => true,
            'consent_date' => now()->format('Y-m-d'),
            'tags' => ['leieforesporsel', $interesse],
            'notes' => "Forespørsel om fremtidig leie\nInteresse: {$interesse}\nØnsket eiendom: " . ($eiendom ?: 'Ikke spesifisert') . "\nMelding: {$melding}\nKilde: {$kilde}",
            'status' => 'active',
        ]);

        // 2) Sak + Min side-tråd + kvittering + varsel (feil her velter ikke nettsiden).
        try {
            $this->lagreOgStart([
                'navn' => $navn, 'epost' => $ep, 'telefon' => $telefon, 'emne' => $emne,
                'melding' => $melding ?: "Forespørsel om {$interesse} leie" . ($eiendom ? ' — ' . $eiendom : ''),
                'kilde' => $kilde,
                'ip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::error('mottaHenvendelse: handleNewEnquiry feilet ' . Str::limit($e->getMessage(), 300, ''));
        }

        return ['success' => true, 'melding' => 'Takk for forespørselen!'];
    }

    /**
     * Lagrer saken synkront og starter resten (kvittering, Min side, varsler) i køen.
     * Kan jobben ikke legges i kø, kjøres behandlingen direkte (originalens fallback).
     */
    private function lagreOgStart(array $opts): void
    {
        $sak = PortalThread::createEnquiryCase($opts);
        try {
            BehandleHenvendelseJobb::dispatch($sak->id, $opts);
        } catch (\Throwable $e) {
            Log::error('mottaHenvendelse: kø feilet (' . Str::limit($e->getMessage(), 200, '') . '), kjører direkte');
            try {
                PortalThread::processEnquiry($sak, $opts);
            } catch (\Throwable $e2) {
                Log::error('processEnquiry ' . Str::limit($e2->getMessage(), 300, ''));
            }
        }
    }

    private function kvittering(bool $engelsk): array
    {
        return [
            'success' => true,
            'melding' => $engelsk
                ? 'Thank you for your message. We will get back to you as soon as we can.'
                : 'Takk for henvendelsen. Den er mottatt, og vi svarer så snart vi kan.',
        ];
    }

    private function tekst(mixed $v, int $maks): string
    {
        if (is_array($v)) {
            $v = '';
        }
        return mb_substr(trim((string) ($v ?? '')), 0, $maks);
    }

    private function erEpost(string $e): bool
    {
        return preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $e) === 1;
    }

    private function erDato(string $d): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
    }

    /** Klient-IP: første adresse i X-Forwarded-For (bak Traefik/nginx), ellers request()->ip(). */
    private function klientIp(array $payload): string
    {
        if (!empty($payload['__ip'])) {
            return (string) $payload['__ip'];
        }
        $xff = (string) request()->header('x-forwarded-for', '');
        $ip = trim(explode(',', $xff)[0] ?? '');
        return $ip !== '' ? $ip : (string) (request()->ip() ?? '');
    }

    // ---------- Offentlig HTTP-svar (CORS som originalen) ----------

    /** @return array<string, string> */
    public static function corsHeaders(Request $request): array
    {
        $origin = (string) $request->headers->get('origin', '');
        $tillatte = self::TILLATTE_OPPHAV;
        foreach (array_filter(array_map('trim', explode(',', (string) config('services.webhooks.motta_henvendelse_origins', '')))) as $o) {
            $tillatte[] = $o;
        }
        $tillatt = (in_array($origin, $tillatte, true) || preg_match('/\.base44\.app$/', $origin)) ? $origin : $tillatte[0];
        return [
            'Access-Control-Allow-Origin' => $tillatt,
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];
    }

    /** JSON-svar for den offentlige POST/OPTIONS-ruten. */
    public function response(Request $request): Response|JsonResponse
    {
        $headers = self::corsHeaders($request);
        if ($request->isMethod('OPTIONS')) {
            return new Response(null, 204, $headers);
        }
        if (!$request->isMethod('POST')) {
            return response()->json(['error' => 'Bruk POST.'], 405, $headers);
        }
        try {
            $payload = $request->json()->all();
            $payload['__ip'] = trim(explode(',', (string) $request->header('x-forwarded-for', ''))[0] ?? '') ?: (string) $request->ip();
            return response()->json($this->__invoke(null, $payload), 200, $headers);
        } catch (FunctionException $e) {
            return response()->json(['error' => $e->getMessage()] + $e->extra, $e->status, $headers);
        } catch (\Throwable $e) {
            report($e);
            Log::error('mottaHenvendelse ' . Str::limit($e->getMessage(), 300, ''));
            return response()->json(
                ['error' => 'Henvendelsen kunne ikke sendes akkurat nå. Prøv igjen om litt, eller send e-post til post@aprop.no.'],
                500,
                $headers
            );
        }
    }
}
