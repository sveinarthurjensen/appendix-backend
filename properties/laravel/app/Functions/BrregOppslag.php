<?php

namespace App\Functions;

use App\Models\AuditEvent;
use App\Models\BrregOppslag as BrregOppslagModel;
use App\Models\LessorCompany;
use App\Models\MaintenanceStaff;
use App\Models\ServicePartner;
use App\Models\User;
use App\Services\Brreg;

/**
 * Portert fra base44/functions/brregOppslag/entry.ts
 *
 * Slår opp en virksomhet i Enhetsregisteret. Uten entity_name/record_id returneres
 * bare oppslaget; med rad lagres et nytt BrregOppslag (er_gjeldende=true, gamle
 * settes til false), TOMME felter på raden fylles ut, og det skrives AuditEvent.
 * Overskriver aldri `name` eller felt noen har fylt ut.
 *
 * Svar: {ok, lagret, oppslag, signaturmerknad, status_varsler[, styret_er_endret,
 *        forrige_oppslag_at, felter_fylt_ut, navneavvik]}
 */
class BrregOppslag extends Base44Function
{
    /** Entiteter som kan slås opp → [vårt felt => felt i oppslaget] som får fylles når tomme. */
    private const ENTITETER = [
        'LessorCompany' => ['org_number' => 'organisasjonsnummer', 'address' => 'fullAdresse'],
        'ServicePartner' => ['org_number' => 'organisasjonsnummer', 'address' => 'fullAdresse'],
        'MaintenanceStaff' => ['org_number' => 'organisasjonsnummer', 'address' => 'fullAdresse'],
    ];

    private const MODELLER = [
        'LessorCompany' => LessorCompany::class,
        'ServicePartner' => ServicePartner::class,
        'MaintenanceStaff' => MaintenanceStaff::class,
    ];

    public function __invoke(?User $user, array $payload): array
    {
        if (!$user?->email) {
            throw new FunctionException('Du må være innlogget.', 401);
        }
        if (!$user->hasRole('admin')) {
            throw new FunctionException('Tilgang nektet: kun admin', 403, ['code' => 'role_denied']);
        }

        try {
            return $this->kjor($user, $payload);
        } catch (FunctionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw new FunctionException('Oppslaget kunne ikke gjennomføres.', 500);
        }
    }

    private function kjor(User $meg, array $payload): array
    {
        $tomt = fn ($v) => trim((string) ($v ?? '')) === '';

        $entitet = trim((string) ($payload['entity_name'] ?? ''));
        $radId = trim((string) ($payload['record_id'] ?? ''));
        $orgnrInn = trim((string) ($payload['org_number'] ?? ''));

        $rad = null;
        if ($entitet !== '' || $radId !== '') {
            if (!isset(self::ENTITETER[$entitet])) {
                throw new FunctionException(
                    "«{$entitet}» kan ikke slås opp. Gyldige: " . implode(', ', array_keys(self::ENTITETER)) . '.',
                    400
                );
            }
            if ($radId === '') {
                throw new FunctionException('record_id mangler.', 400);
            }
            $rad = (self::MODELLER[$entitet])::find($radId);
            if (!$rad) {
                throw new FunctionException('Fant ikke raden.', 404);
            }
            // En privatperson som leier ut har ikke organisasjonsnummer.
            if ($entitet === 'LessorCompany' && $rad->is_private === true) {
                throw new FunctionException(
                    ($rad->name ?: 'Utleieren') . ' er registrert som privatperson. Enhetsregisteret gjelder virksomheter.',
                    422,
                    ['kan_slaas_opp' => false]
                );
            }
        }

        $orgnr = $orgnrInn !== '' ? $orgnrInn : (string) ($rad?->org_number ?? '');
        $vurdering = Brreg::vurderOrgnr($orgnr);
        if ($vurdering !== 'norsk') {
            // 422 for utenlandske: nummeret er ikke nødvendigvis feil, bare ikke oppslagbart.
            throw new FunctionException(
                Brreg::forklarNummer($orgnr),
                $vurdering === 'ugyldig' ? 400 : 422,
                ['vurdering' => $vurdering, 'kan_slaas_opp' => false]
            );
        }

        try {
            $oppslag = app(Brreg::class)->hentEnhet($orgnr);
        } catch (\RuntimeException $e) {
            throw new FunctionException($e->getMessage(), 400);
        }

        $naa = now();
        $merknad = Brreg::signaturmerknad($oppslag);
        $varsler = Brreg::statusvarsler($oppslag);

        if (!$rad) {
            return [
                'ok' => true, 'lagret' => false, 'oppslag' => $oppslag,
                'signaturmerknad' => $merknad, 'status_varsler' => $varsler,
            ];
        }

        $forrige = BrregOppslagModel::base44Filter([
            'entity_name' => $entitet, 'record_id' => $rad->id, 'er_gjeldende' => true,
        ])->get();

        $gammelSistEndret = (string) ($forrige->first()?->styre_sist_endret ?? '');
        $styretErEndret = $gammelSistEndret !== ''
            && $oppslag['styreSistEndret'] !== ''
            && $gammelSistEndret !== $oppslag['styreSistEndret'];

        $somRad = fn (array $p) => [
            'navn' => $p['navn'],
            'rolle' => $p['rolle'],
            'rollekode' => $p['rollekode'],
            'er_virksomhet' => $p['erVirksomhet'],
            'organisasjonsnummer' => $p['organisasjonsnummer'],
            'fodselsaar' => $p['fodselsaar'],
        ];

        BrregOppslagModel::create([
            'entity_name' => $entitet,
            'record_id' => $rad->id,
            'org_number' => Brreg::normaliserOrgnr($orgnr),
            'er_gjeldende' => true,
            'navn' => $oppslag['navn'],
            'organisasjonsform' => $oppslag['organisasjonsform'],
            'organisasjonsform_kode' => $oppslag['organisasjonsformKode'],
            'forretningsadresse' => $oppslag['forretningsadresse'],
            'postnummer' => $oppslag['postnummer'],
            'poststed' => $oppslag['poststed'],
            'land' => $oppslag['land'],
            'hjemmeside' => $oppslag['hjemmeside'],
            'epostadresse' => $oppslag['epostadresse'],
            'telefon' => $oppslag['telefon'],
            'naeringskode' => $oppslag['naeringskode'],
            'naeringsbeskrivelse' => $oppslag['naeringsbeskrivelse'],
            'antall_ansatte' => $oppslag['antallAnsatte'],
            'stiftelsesdato' => $oppslag['stiftelsesdato'],
            'registreringsdato' => $oppslag['registreringsdato'],
            'registrert_i_foretaksregisteret' => $oppslag['registrertIForetaksregisteret'],
            'registrert_i_mvaregisteret' => $oppslag['registrertIMvaregisteret'],
            'under_avvikling' => $oppslag['underAvvikling'],
            'under_tvangsavvikling' => $oppslag['underTvangsavvikling'],
            'konkurs' => $oppslag['konkurs'],
            'styre' => array_map($somRad, $oppslag['styre']),
            'styre_sist_endret' => $oppslag['styreSistEndret'],
            'daglig_leder' => array_map($somRad, $oppslag['dagligLeder']),
            'signatur' => array_map($somRad, $oppslag['signatur']),
            'prokura' => array_map($somRad, $oppslag['prokura']),
            'revisor' => array_map($somRad, $oppslag['revisor']),
            'regnskapsforer' => array_map($somRad, $oppslag['regnskapsforer']),
            'signaturmerknad' => $merknad,
            'status_varsler' => $varsler,
            'oppslag_at' => $naa,
            'oppslag_av' => $meg->email,
        ]);

        // Først etter at det nye er skrevet – motsatt rekkefølge kunne etterlatt null gjeldende.
        foreach ($forrige as $f) {
            try {
                $f->update(['er_gjeldende' => false]);
            } catch (\Throwable) {
            }
        }

        $fullAdresse = implode(', ', array_filter([
            $oppslag['forretningsadresse'],
            implode(' ', array_filter([$oppslag['postnummer'], $oppslag['poststed']])),
        ]));
        $kilde = $oppslag + ['fullAdresse' => $fullAdresse];

        $patch = [];
        foreach (self::ENTITETER[$entitet] as $vaart => $deres) {
            $verdi = $kilde[$deres] ?? null;
            if ($tomt($rad->{$vaart}) && !$tomt($verdi)) {
                $patch[$vaart] = $verdi;
            }
        }
        if ($entitet === 'LessorCompany'
            && !is_numeric($rad->employees) && $oppslag['antallAnsatte'] !== null) {
            $patch['employees'] = $oppslag['antallAnsatte'];
        }
        if ($patch) {
            $rad->update($patch);
        }

        $vaartNavn = (string) ($rad->name ?? '');
        $navneavvik = $vaartNavn !== '' && mb_strtolower($vaartNavn) !== mb_strtolower($oppslag['navn'])
            ? ['hos_oss' => $vaartNavn, 'i_registeret' => $oppslag['navn']]
            : null;

        try {
            AuditEvent::create([
                'event_type' => 'brreg_oppslag',
                'actor_user_id' => $meg->id,
                'actor_email' => $meg->email,
                'ip' => request()?->ip() ?: 'ukjent',
                'detail' => "{$entitet}/{$rad->id}: slo opp " . Brreg::normaliserOrgnr($orgnr) . " ({$oppslag['navn']}) "
                    . 'i Enhetsregisteret. ' . count($oppslag['styre']) . ' styremedlem(mer), styre sist endret '
                    . ($oppslag['styreSistEndret'] ?: 'ukjent') . '.'
                    . ($styretErEndret ? " STYRET ER ENDRET siden {$gammelSistEndret}." : '')
                    . ($patch ? ' Fylte ut: ' . implode(', ', array_keys($patch)) . '.' : '')
                    . ($navneavvik ? " Navneavvik: «{$navneavvik['hos_oss']}» hos oss." : '')
                    . ($varsler ? ' ' . implode(' ', $varsler) : ''),
                'success' => true,
                'created_at' => $naa,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'ok' => true,
            'lagret' => true,
            'oppslag' => $oppslag,
            'signaturmerknad' => $merknad,
            'status_varsler' => $varsler,
            'styret_er_endret' => $styretErEndret,
            'forrige_oppslag_at' => $forrige->first()?->oppslag_at?->toISOString(),
            'felter_fylt_ut' => array_keys($patch),
            'navneavvik' => $navneavvik,
        ];
    }
}
