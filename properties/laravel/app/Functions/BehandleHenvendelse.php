<?php

namespace App\Functions;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\PortalThread;
use Carbon\Carbon;

/**
 * Portert fra base44/functions/behandleHenvendelse/entry.ts
 *
 * Bakgrunnssteg for mottaHenvendelse: kvittering + Min side-meldingstråd + varsel til ansatte
 * (e-post + SMS). I Base44 ble den kalt over HTTP (uten innlogging) av mottaHenvendelse; her kjøres
 * den av jobben App\Jobs\BehandleHenvendelse i køen (Horizon). Den er derfor IKKE lagt ut som
 * offentlig rute – via POST /api/functions/behandleHenvendelse krever den innlogging som alt annet.
 *
 * Kun nyopprettede saker kan behandles, og bare én gang:
 *   - saken må finnes, være case_type «henvendelse», merket «nettside»
 *   - opprettet for under 10 minutter siden
 *   - e-posten i forespørselen må være lik counterpart_email på saken
 *   - saken må ikke allerede være merket «behandlet» / «behandles»
 *
 * Svar: {ok: true, identified, invite_sent, obs}
 */
class BehandleHenvendelse extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $caseId = (string) ($payload['case_id'] ?? '');
        $opts = is_array($payload['opts'] ?? null) ? $payload['opts'] : [];
        if ($caseId === '' || empty($opts['epost'])) {
            throw new FunctionException('mangler data', 400);
        }

        $sak = CaseRecord::find($caseId);
        if (!$sak || $sak->case_type !== 'henvendelse') {
            throw new FunctionException('ukjent sak', 404);
        }

        $tags = $sak->tags ?? [];
        $alderMs = $sak->created_date ? now()->diffInMilliseconds(Carbon::parse($sak->created_date), true) : PHP_INT_MAX;
        if (!in_array('nettside', $tags, true) || in_array('behandlet', $tags, true) || in_array('behandles', $tags, true) || !($alderMs < 10 * 60 * 1000)) {
            throw new FunctionException('kan ikke behandles', 409);
        }
        if (strtolower((string) $sak->counterpart_email) !== strtolower((string) $opts['epost'])) {
            throw new FunctionException('stemmer ikke', 409);
        }

        // Lås mot dobbel behandling.
        try {
            $sak->update(['tags' => array_merge($tags, ['behandles'])]);
        } catch (\Throwable) {
        }

        $res = PortalThread::processEnquiry($sak, [
            'navn' => (string) ($opts['navn'] ?? ''),
            'epost' => (string) $opts['epost'],
            'telefon' => (string) ($opts['telefon'] ?? ''),
            'emne' => (string) ($opts['emne'] ?? ''),
            'melding' => (string) ($opts['melding'] ?? ''),
            'kilde' => (string) ($opts['kilde'] ?? ''),
        ]);

        return [
            'ok' => true,
            'identified' => (bool) ($res['identified'] ?? false),
            'invite_sent' => (bool) ($res['invite_sent'] ?? false),
            'obs' => (bool) ($res['obs'] ?? false),
        ];
    }
}
