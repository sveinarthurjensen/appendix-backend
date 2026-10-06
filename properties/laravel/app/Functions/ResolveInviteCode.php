<?php

namespace App\Functions;

use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteEvents;

/**
 * Portert fra base44/functions/resolveInviteCode/entry.ts
 *
 * Resolver en kort invite-kode (/i/<kode>, InviteRedirect.jsx) til invite-token for
 * accept-invite-flyten. Offentlig — kalles av uinnlogget mottaker fra SMS-lenke.
 * Svar: {valid: true, token, name, role} eller {valid: false} (400 ved manglende kode, 404 ved ukjent).
 */
class ResolveInviteCode extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $code = $payload['code'] ?? null;
        if (!$code || !is_string($code)) {
            throw new FunctionException('Ugyldig kode', 400, ['valid' => false]);
        }

        $inv = UserInvite::where('short_code', trim($code))->first();
        if (!$inv) {
            throw new FunctionException('Invitasjon ikke funnet', 404, ['valid' => false]);
        }
        if ($inv->status === 'cancelled' || $inv->status === 'used') {
            return ['valid' => false];
        }
        if (InviteEvents::isExpired($inv->expires_at)) {
            try { $inv->update(['status' => 'expired']); } catch (\Throwable) {}
            InviteEvents::log([
                'invite_id' => $inv->id,
                'event_type' => 'utløpt',
                'actor_name' => 'System',
                'invite_email' => $inv->email ?: '',
                'detail' => 'Invitasjonen utløp da mottaker åpnet lenken',
            ]);
            return ['valid' => false];
        }

        InviteEvents::log([
            'invite_id' => $inv->id,
            'event_type' => 'åpnet',
            'actor_name' => $inv->email ?: 'Mottaker',
            'invite_email' => $inv->email ?: '',
            'detail' => 'BankID-lenke åpnet av mottaker',
        ]);

        return ['valid' => true, 'token' => $inv->token, 'name' => $inv->name ?: '', 'role' => $inv->role];
    }
}
