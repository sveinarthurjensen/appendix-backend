<?php

namespace App\Functions;

use App\Models\Invitation;
use App\Models\User;
use App\Services\InviteEvents;

/**
 * Portert fra base44/functions/autoApproveInvitedUser/entry.ts
 *
 * Kalles av /register (Register.jsx) etter at brukeren har opprettet konto og logget inn.
 * Innlogget brukers e-post matches mot en Invitation (status 'sent'):
 *  - sett admin_approved + onboarding_completed på User
 *  - kopier rolle, navn, mobil (og evt. forhåndsregistrert fnr fra Vei 3) til User
 *  - Invitation.status='registered', registered_user_id=me.id
 * Idempotent: allerede registrert for samme bruker → status uten nye skriv.
 * Svar: {success, role, bankid_stepup_required[, already_registered]}.
 */
class AutoApproveInvitedUser extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $me = $this->requireUser($user);

        $list = Invitation::where('email', $me->email)->get();

        $already = $list->first(fn ($i) => $i->status === 'registered' && $i->registered_user_id === $me->id);
        if ($already) {
            return [
                'success' => true,
                'already_registered' => true,
                'role' => $already->role,
                'bankid_stepup_required' => !$me->bankid_verified,
            ];
        }

        $inv = $list->first(fn ($i) => $i->status === 'sent');
        if (!$inv) {
            throw new FunctionException('Ingen ventende invitasjon for din e-post', 404);
        }

        $patch = ['admin_approved' => true, 'onboarding_completed' => true];
        if ($inv->role && $me->role !== $inv->role) {
            $patch['role'] = $inv->role;
        }
        if (!$me->full_name && $inv->full_name) {
            $patch['full_name'] = $inv->full_name;
        }
        // Kopier mobil fra invitasjonen til brukeren (norsk telefonnummer for SMS-varsling).
        if ($inv->mobile && !$me->mobile) {
            $patch['mobile'] = $inv->mobile;
        }
        // Vei 3: kopier forhåndsregistrert fnr fra invitasjonen til brukeren.
        if ($inv->national_id_hash) {
            $patch['national_id_hash'] = $inv->national_id_hash;
            if ($inv->national_id_last4) {
                $patch['national_id_last4'] = $inv->national_id_last4;
            }
            $patch['national_id_source'] = 'invitation';
        }

        $me->update($patch);
        $inv->update(['status' => 'registered', 'registered_user_id' => $me->id]);

        InviteEvents::audit([
            'event_type' => 'invite_akseptert',
            'actor_email' => $me->email,
            'detail' => "Registrert med passord — invitasjon godkjent — {$inv->email}",
            'success' => true,
        ]);

        return [
            'success' => true,
            'role' => $inv->role,
            'bankid_stepup_required' => !$me->bankid_verified,
        ];
    }
}
