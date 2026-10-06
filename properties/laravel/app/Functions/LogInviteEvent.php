<?php

namespace App\Functions;

use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteEvents;

/**
 * Portert fra base44/functions/logInviteEvent/entry.ts
 *
 * HTTP-wrapper for invitasjonshendelser fra frontend (admin, UserInvites.jsx/InviteManager.jsx).
 * Brukes for "sendt" (etter at SMS er dispatchet) og "tilbakekalt" (admin trekker tilbake).
 * Hendelser serverside (opprettet, åpnet, akseptert, utløpt) logges i sine egne funksjoner.
 * Svar: {success: true}.
 */
class LogInviteEvent extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan logge invitasjonshendelser', 403);
        }

        $inviteId = $payload['invite_id'] ?? null;
        $eventType = $payload['event_type'] ?? null;
        if (!$inviteId || !$eventType) {
            throw new FunctionException('invite_id og event_type er påkrevd', 400);
        }

        // For "tilbakekalt": marker invitasjonen som cancelled.
        if ($eventType === 'tilbakekalt') {
            try {
                UserInvite::find($inviteId)?->update(['status' => 'cancelled']);
            } catch (\Throwable) {}
        }

        InviteEvents::log([
            'invite_id' => (string) $inviteId,
            'event_type' => (string) $eventType,
            'actor_name' => $user->email,
            'actor_email' => $user->email,
            'invite_email' => $payload['invite_email'] ?? '',
            'detail' => $payload['detail'] ?? '',
        ]);

        return ['success' => true];
    }
}
