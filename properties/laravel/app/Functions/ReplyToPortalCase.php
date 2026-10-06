<?php

namespace App\Functions;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\PortalThread;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Portert fra base44/functions/replyToPortalCase/entry.ts
 *
 * Ansatt (admin) svarer på en sak i Saksbehandling. Svaret legges som utgående
 * melding på motpartens Min side-tråd, og motparten får SMS + e-post
 * «Du har fått svar …» – aldri selve innholdet.
 */
class ReplyToPortalCase extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan svare på saker', 403);
        }

        $caseId = $payload['case_id'] ?? null;
        $message = $payload['message'] ?? null;
        if (!$caseId || !$message || trim((string) $message) === '') {
            throw new FunctionException('Mangler case_id eller melding', 400);
        }

        $sak = CaseRecord::base44Filter(['id' => $caseId])->first();
        if (!$sak) {
            throw new FunctionException('Sak ikke funnet', 404);
        }
        if (!$sak->counterpart_email) {
            throw new FunctionException('Saken har ingen motpart (e-post mangler)', 400);
        }

        // Motpartens mobil (for SMS-varsel) fra User om hun finnes.
        $mobil = '';
        try {
            $u = User::where('email', $sak->counterpart_email)->first();
            if ($u) {
                $mobil = $u->mobile ?: '';
            }
        } catch (\Throwable) {}

        // 1) Legg svaret på Min side-tråden (utgående).
        $msg = PortalThread::postPortalMessage([
            'case_id' => $sak->id,
            'case_number' => $sak->case_number,
            'subject' => $sak->title,
            'user_email' => $sak->counterpart_email,
            'direction' => 'utgående',
            'message' => Str::substr(trim((string) $message), 0, 5000),
            'channel' => 'saksbehandling',
            'sender_name' => $user->full_name ?: $user->email,
        ]);

        // 2) Oppdater saksstatus.
        try {
            $sak->update(['status' => 'avventer_svar']);
        } catch (\Throwable) {}

        // 3) Varsle motparten – feil her velter ikke svaret.
        try {
            PortalThread::sendCounterpartReplyNotification($sak->counterpart_email, $mobil);
        } catch (\Throwable $e) {
            Log::error('replyToPortalCase: varsel feilet ' . Str::limit($e->getMessage(), 300, ''));
        }

        return ['success' => true, 'message' => $msg->toBase44Array()];
    }
}
