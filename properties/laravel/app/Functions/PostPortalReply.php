<?php

namespace App\Functions;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\PortalThread;
use Illuminate\Support\Str;

/**
 * Portert fra base44/functions/postPortalReply/entry.ts
 *
 * Gjest/leietaker svarer fra Min side på en egen henvendelse. Saken verifiseres
 * mot actor.email – hun kan kun svare på egne saker (aldri stol på case_id alene).
 */
class PostPortalReply extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $actor = $this->requireUser($user);
        if (!$actor->email) {
            throw new FunctionException('Bruker mangler e-post', 400);
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
        if (strtolower((string) $sak->counterpart_email) !== strtolower((string) $actor->email)) {
            throw new FunctionException('Ingen tilgang til denne saken', 403);
        }

        $msg = PortalThread::postPortalMessage([
            'case_id' => $sak->id,
            'case_number' => $sak->case_number,
            'subject' => $sak->title,
            'user_email' => $actor->email,
            'user_id' => $actor->id,
            'direction' => 'innkommende',
            'message' => Str::substr(trim((string) $message), 0, 5000),
            'channel' => 'minside',
            'sender_name' => $actor->full_name ?: $actor->email,
        ]);

        // Sett saken tilbake til «ny» slik at ansatt ser aktivitet i Saksbehandling.
        try {
            $sak->update(['status' => 'ny']);
        } catch (\Throwable) {}

        return ['success' => true, 'message' => $msg->toBase44Array()];
    }
}
