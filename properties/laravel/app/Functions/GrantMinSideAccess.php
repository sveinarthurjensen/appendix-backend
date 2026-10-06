<?php

namespace App\Functions;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\PortalThread;

/**
 * Portert fra base44/functions/grantMinSideAccess/entry.ts
 *
 * Gir en person tilgang til Min side (gjest-bruker + BankID-invitasjon på SMS).
 * Kalles fra: (1) «Gi tilgang til Min side» på en henvendelse (case_id),
 * (2) når en booking bekreftes (name/email/mobile direkte). Kun admin.
 *
 * Avvik: originalen avviste ikke-POST med 405; HTTP-metode håndteres av ruteren her.
 * Originalen returnerte {error:'internal_error'} (500) på uventede feil – her lar vi
 * FunctionException/unntak boble som i de andre funksjonene.
 */
class GrantMinSideAccess extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan gi Min side-tilgang', 403);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $mobile = trim((string) ($payload['mobile'] ?? ''));
        $caseId = '';

        // Fra henvendelse: hent navn/e-post/telefon fra saken.
        if (!empty($payload['case_id'])) {
            $caseId = (string) $payload['case_id'];
            $sak = CaseRecord::find($caseId);
            if (!$sak) {
                throw new FunctionException('Sak ikke funnet', 404);
            }
            $tags = $sak->tags ?? [];
            if (in_array('minside-aktivert', $tags, true)) {
                return ['ok' => true, 'already' => true];
            }
            $name = (string) ($sak->counterpart_name ?: '');
            $email = strtolower(trim((string) ($sak->counterpart_email ?: '')));
            $mobile = preg_match('/Telefon:\s*([^\n\r]+)/', (string) $sak->description, $m) ? trim($m[1]) : '';
            if ($mobile === '—' || !$mobile) {
                $mobile = '';
            }
        }

        if (!$email) {
            throw new FunctionException('Mangler e-post på motparten.', 400);
        }
        if (!$mobile) {
            throw new FunctionException('Mangler telefon. Legg til telefon på motparten før du gir tilgang.', 400);
        }

        $res = PortalThread::autoProvisionGuestFromEnquiry($name, $email, $mobile);

        // Merk saken som aktivert (kun fra case_id-stien).
        if (($res['identified'] || $res['invite_sent']) && $caseId) {
            try {
                $sak = CaseRecord::find($caseId);
                $tags = $sak?->tags ?? [];
                if ($sak && !in_array('minside-aktivert', $tags, true)) {
                    $sak->update(['tags' => [...$tags, 'minside-aktivert']]);
                }
            } catch (\Throwable) {}
        }

        return ['ok' => true, 'identified' => $res['identified'], 'invite_sent' => $res['invite_sent'], 'error' => $res['error']];
    }
}
