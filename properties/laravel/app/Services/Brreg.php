<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/shared/brreg.ts.
 *
 * Enhetsregisteret — hvem er virksomheten, og hvem står oppført for den?
 * Åpent, gratis, uten nøkkel. Alle rollegrupper tolkes (ikke bare styret),
 * utenlandske numre skilles fra ugyldige, og selve signaturteksten finnes ikke
 * i den åpne tjenesten. Av fødselsdato lagres bare året.
 *
 * Konfig: services.brreg.base (standard https://data.brreg.no/enhetsregisteret/api)
 *
 * Et «Enhetsoppslag» er et assosiativt array med NØYAKTIG samme nøkler som
 * TypeScript-grensesnittet Enhetsoppslag (camelCase), slik at frontenden
 * får samme JSON som før.
 */
class Brreg
{
    /** Tar bare sifrene. «921 652 690» og «921652690» er samme nummer. */
    public static function normaliserOrgnr(mixed $raa): string
    {
        return preg_replace('/\D/', '', (string) ($raa ?? ''));
    }

    /** Ni siffer med gyldig mod-11-kontrollsiffer — altså et norsk nummer. */
    public static function gyldigOrgnr(mixed $raa): bool
    {
        $n = self::normaliserOrgnr($raa);
        if (strlen($n) !== 9) {
            return false;
        }
        $vekt = [3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($vekt as $i => $v) {
            $sum += $v * (int) $n[$i];
        }
        $rest = $sum % 11;
        $kontroll = $rest === 0 ? 0 : 11 - $rest;
        if ($kontroll === 10) {
            return false;
        }
        return $kontroll === (int) $n[8];
    }

    /** @return 'norsk'|'utenlandsk'|'tomt'|'ugyldig' */
    public static function vurderOrgnr(mixed $raa): string
    {
        $n = self::normaliserOrgnr($raa);
        if ($n === '') {
            return 'tomt';
        }
        if (strlen($n) !== 9) {
            return 'utenlandsk';
        }
        return self::gyldigOrgnr($n) ? 'norsk' : 'ugyldig';
    }

    public static function forklarNummer(mixed $raa): string
    {
        return match (self::vurderOrgnr($raa)) {
            'tomt' => 'Ingen organisasjonsnummer er ført inn.',
            'utenlandsk' => "«{$raa}» har ikke ni siffer og er trolig utenlandsk. "
                . 'Enhetsregisteret dekker bare norske virksomheter, så denne raden '
                . 'må vedlikeholdes manuelt.',
            'ugyldig' => "«{$raa}» har ni siffer, men kontrollsifferet stemmer ikke. "
                . 'Det er nesten alltid en tastefeil — sjekk nummeret før oppslag.',
            default => '',
        };
    }

    private static function tekst(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    /** Fødselsåret alene. Full dato lagres ikke. */
    private static function aaret(mixed $fodselsdato): ?int
    {
        return preg_match('/^(\d{4})-/', self::tekst($fodselsdato), $m) ? (int) $m[1] : null;
    }

    private static function navnetTil(array $r): string
    {
        if (!empty($r['enhet'])) {
            $navn = $r['enhet']['navn'] ?? null;
            if (is_array($navn)) {
                $deler = array_filter(array_map(
                    fn ($k) => self::tekst($navn[$k] ?? ''),
                    ['navn1', 'navn2', 'navn3']
                ));
                return implode(' ', $deler);
            }
            return self::tekst($navn);
        }
        $p = $r['person']['navn'] ?? [];
        $deler = array_filter(array_map(
            fn ($k) => self::tekst($p[$k] ?? ''),
            ['fornavn', 'mellomnavn', 'etternavn']
        ));
        return implode(' ', $deler);
    }

    /**
     * Tolk hele rollesvaret. Avregistrerte og fratrådte roller filtreres bort.
     * @return array<int, array{kode:string,beskrivelse:string,sistEndret:string,personer:array}>
     */
    public static function tolkRoller(?array $rollesvar): array
    {
        $ut = [];
        foreach ($rollesvar['rollegrupper'] ?? [] as $g) {
            $kode = self::tekst($g['type']['kode'] ?? '');
            if ($kode === '') {
                continue;
            }
            $personer = [];
            foreach ($g['roller'] ?? [] as $r) {
                if (($r['avregistrert'] ?? false) === true || ($r['fratraadt'] ?? false) === true) {
                    continue;
                }
                $erVirksomhet = !empty($r['enhet']);
                $p = [
                    'navn' => self::navnetTil($r),
                    'rolle' => self::tekst($r['type']['beskrivelse'] ?? ''),
                    'rollekode' => self::tekst($r['type']['kode'] ?? ''),
                    'erVirksomhet' => $erVirksomhet,
                    'organisasjonsnummer' => self::tekst($r['enhet']['organisasjonsnummer'] ?? ''),
                    'fodselsaar' => $erVirksomhet ? null : self::aaret($r['person']['fodselsdato'] ?? null),
                ];
                if ($p['navn'] !== '') {
                    $personer[] = $p;
                }
            }
            $ut[] = [
                'kode' => $kode,
                'beskrivelse' => self::tekst($g['type']['beskrivelse'] ?? ''),
                'sistEndret' => self::tekst($g['sistEndret'] ?? ''),
                'personer' => $personer,
            ];
        }
        return $ut;
    }

    private static function gruppe(array $grupper, string $kode): ?array
    {
        foreach ($grupper as $g) {
            if ($g['kode'] === $kode) {
                return $g;
            }
        }
        return null;
    }

    private static function personerI(array $grupper, string $kode): array
    {
        return self::gruppe($grupper, $kode)['personer'] ?? [];
    }

    /**
     * Hent en virksomhet fra Enhetsregisteret.
     * Kaster RuntimeException ved ugyldig/utenlandsk nummer og når registeret ikke svarer.
     */
    public function hentEnhet(mixed $orgnr): array
    {
        if (self::vurderOrgnr($orgnr) !== 'norsk') {
            throw new \RuntimeException(self::forklarNummer($orgnr));
        }
        $n = self::normaliserOrgnr($orgnr);
        $api = rtrim((string) config('services.brreg.base', 'https://data.brreg.no/enhetsregisteret/api'), '/') . '/enheter';

        try {
            // Parallelt som Promise.all i originalen. En feilet forespørsel i en pool
            // kommer tilbake som exception-objekt, ikke som Response.
            [$eRes, $rRes] = Http::pool(fn ($pool) => [
                $pool->acceptJson()->timeout(20)->get("{$api}/{$n}"),
                $pool->acceptJson()->timeout(20)->get("{$api}/{$n}/roller"),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Enhetsregisteret svarte ikke: ' . $e->getMessage());
        }
        if ($eRes instanceof \Throwable) {
            throw new \RuntimeException('Enhetsregisteret svarte ikke: ' . $eRes->getMessage());
        }
        if ($eRes->status() === 404) {
            throw new \RuntimeException("Fant ingen virksomhet med organisasjonsnummer {$n}.");
        }
        if (!$eRes->ok()) {
            throw new \RuntimeException("Enhetsregisteret svarte {$eRes->status()}.");
        }

        $e = $eRes->json() ?? [];
        // Roller kan mangle uten at enheten gjør det – tomme grupper er et gyldig svar.
        $grupper = (!($rRes instanceof \Throwable) && $rRes->ok()) ? self::tolkRoller($rRes->json()) : [];

        $adr = $e['forretningsadresse'] ?? $e['beliggenhetsadresse'] ?? [];
        $iForetaksregisteret = ($e['registrertIForetaksregisteret'] ?? false) === true;
        $antall = $e['antallAnsatte'] ?? null;

        return [
            'organisasjonsnummer' => self::tekst($e['organisasjonsnummer'] ?? ''),
            'navn' => self::tekst($e['navn'] ?? ''),
            'organisasjonsform' => self::tekst($e['organisasjonsform']['beskrivelse'] ?? ''),
            'organisasjonsformKode' => self::tekst($e['organisasjonsform']['kode'] ?? ''),
            'hjemmeside' => self::tekst($e['hjemmeside'] ?? ''),
            'epostadresse' => self::tekst($e['epostadresse'] ?? ''),
            'telefon' => self::tekst($e['telefon'] ?? '') ?: self::tekst($e['mobil'] ?? ''),
            'forretningsadresse' => implode(', ', array_filter(array_map([self::class, 'tekst'], $adr['adresse'] ?? []))),
            'postnummer' => self::tekst($adr['postnummer'] ?? ''),
            'poststed' => self::tekst($adr['poststed'] ?? ''),
            'land' => self::tekst($adr['land'] ?? '') ?: 'Norge',
            'naeringskode' => self::tekst($e['naeringskode1']['kode'] ?? ''),
            'naeringsbeskrivelse' => self::tekst($e['naeringskode1']['beskrivelse'] ?? ''),
            'antallAnsatte' => is_int($antall) || is_float($antall) ? $antall : null,
            'stiftelsesdato' => self::tekst($e['stiftelsesdato'] ?? ''),
            'registreringsdato' => self::tekst($e['registreringsdatoEnhetsregisteret'] ?? ''),
            'registrertIForetaksregisteret' => $iForetaksregisteret,
            'registrertIMvaregisteret' => ($e['registrertIMvaregisteret'] ?? false) === true,
            'underAvvikling' => ($e['underAvvikling'] ?? false) === true,
            'underTvangsavvikling' => ($e['underTvangsavviklingEllerTvangsopplosning'] ?? false) === true,
            'konkurs' => ($e['konkurs'] ?? false) === true,
            'grupper' => $grupper,
            'styre' => self::personerI($grupper, 'STYR'),
            'styreSistEndret' => self::gruppe($grupper, 'STYR')['sistEndret'] ?? '',
            'dagligLeder' => self::personerI($grupper, 'DAGL'),
            'signatur' => self::personerI($grupper, 'SIGN'),
            'prokura' => self::personerI($grupper, 'PROK'),
            'revisor' => self::personerI($grupper, 'REVI'),
            'regnskapsforer' => self::personerI($grupper, 'REGN'),
            'kanHaRegistrertSignatur' => $iForetaksregisteret,
        ];
    }

    /** Hva brukeren skal få vite om signaturretten. */
    public static function signaturmerknad(array $o): string
    {
        if (!$o['kanHaRegistrertSignatur']) {
            return "{$o['navn']} står ikke i Foretaksregisteret. Signaturrett registreres "
                . 'bare der, så det finnes ingen offentlig registrert signaturrett for '
                . 'denne virksomheten. Hvem som kan binde den følger av vedtektene.';
        }
        if (count($o['signatur'])) {
            return 'Signaturrett er registrert på: '
                . implode(', ', array_map(fn ($p) => $p['navn'], $o['signatur']))
                . '. Selve ordlyden («i fellesskap», «hver for seg») ligger i '
                . 'Foretaksregisteret og følger ikke med i dette oppslaget — den må leses '
                . 'der og føres inn ordrett.';
        }
        return 'Virksomheten står i Foretaksregisteret, men oppslaget ga ingen '
            . 'signaturrolle. Signaturbestemmelsen må hentes fra Foretaksregisteret og '
            . 'føres inn manuelt.';
    }

    /** Noe det haster å vite: er selskapet i ferd med å forsvinne? */
    public static function statusvarsler(array $o): array
    {
        $ut = [];
        if ($o['konkurs']) {
            $ut[] = 'Registrert som KONKURS i Enhetsregisteret.';
        }
        if ($o['underTvangsavvikling']) {
            $ut[] = 'Under tvangsavvikling eller tvangsoppløsning.';
        }
        if ($o['underAvvikling']) {
            $ut[] = 'Under avvikling.';
        }
        if (!count($o['styre']) && !count($o['dagligLeder'])) {
            $ut[] = 'Verken styre eller daglig leder er registrert.';
        }
        return $ut;
    }
}
