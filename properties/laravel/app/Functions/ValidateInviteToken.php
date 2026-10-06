<?php

namespace App\Functions;

use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteEvents;

/**
 * Portert fra base44/functions/validateInviteToken/entry.ts
 *
 * Offentlig (ingen auth) — validerer invite-token fra /accept-invite-lenken (AcceptInvite.jsx).
 * Svar: {valid: false, reason} eller {valid: true, name, email, role}. Alltid HTTP 200 (ugyldig
 * token er ikke en feil).
 *
 * Avvik: originalen leste token fra query-string ELLER JSON-kropp; her kun fra $payload['token']
 * (ruteren kan legge query-parametre inn i payload om nødvendig).
 */
class ValidateInviteToken extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $token = (string) ($payload['token'] ?? '');
        if ($token === '') {
            return ['valid' => false, 'reason' => 'missing'];
        }

        $inv = UserInvite::where('token', $token)->first();
        if (!$inv) {
            return ['valid' => false, 'reason' => 'not_found'];
        }
        if ($inv->status === 'used') {
            return ['valid' => false, 'reason' => 'already_used'];
        }
        if (InviteEvents::isExpired($inv->expires_at)) {
            return ['valid' => false, 'reason' => 'expired'];
        }
        if ($inv->status !== 'sent' && $inv->status !== 'draft') {
            return ['valid' => false, 'reason' => $inv->status];
        }

        return ['valid' => true, 'name' => $inv->name, 'email' => $inv->email, 'role' => $inv->role];
    }
}
