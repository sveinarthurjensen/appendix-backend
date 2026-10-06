<?php

namespace App\Functions;

use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteEvents;
use App\Services\PortalThread;
use Illuminate\Support\Str;

/**
 * Portert fra base44/functions/provisionUserInvite/entry.ts
 *
 * Forhåndsprovisjoner en invitasjon: opprett en EKTE bruker med invitasjonens e-post + rolle
 * — INGEN e-post sendes (KUN SMS, og SMS-en sendes manuelt av admin etterpå). Setter
 * target_user_id, status 'sent', og returnerer SMS-tekst + BankID-lenke.
 *
 * Avvik (brukeropprettelse): Base44 opprettet User via entities.User.create. Her opprettes
 * App\Models\User med id = ulid, app_id = 'appendix_properties', role fra invitasjonen og
 * password = null (brukeren logger inn via BankID/OIDC senere). nin_hash/bankid-binding
 * settes av OIDC-callbacken ved første BankID-innlogging, som i originalen.
 */
class ProvisionUserInvite extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan godkjenne invitasjoner', 403);
        }

        $inv = UserInvite::find($payload['invite_id'] ?? '');
        if (!$inv) {
            throw new FunctionException('Invitasjon ikke funnet', 404);
        }
        if ($inv->status !== 'draft') {
            throw new FunctionException("Invitasjon har status {$inv->status}", 400);
        }
        if (InviteEvents::isExpired($inv->expires_at)) {
            $inv->update(['status' => 'expired']);
            throw new FunctionException('Invitasjonen er utløpt', 400);
        }
        if (!$inv->email) {
            throw new FunctionException('E-post kreves for å forhåndsprovisjonere konto for BankID-innlogging', 400);
        }

        $now = now();
        $targetUserId = '';
        $createError = '';

        // 1) Finn eksisterende User via e-post, ellers opprett (uten plattform-e-post).
        $targetUser = User::where('email', $inv->email)->first();
        if (!$targetUser) {
            try {
                $targetUser = User::create([
                    'id' => strtolower((string) Str::ulid()),
                    'app_id' => 'appendix_properties',
                    'email' => $inv->email,
                    'full_name' => $inv->name ?: '',
                    'role' => $inv->role,
                    'password' => null,
                    'nin_hash' => '',
                    'nin_verified_at' => null,
                    'bankid_verified_at' => null,
                    'bankid_verified' => false,
                    'nin_level' => 1,
                ]);
            } catch (\Throwable $e) {
                $createError = $e->getMessage() ?: (string) $e;
            }
        }

        // 2) Sett valgt rolle på eksisterende/ny bruker.
        if ($targetUser) {
            $targetUserId = $targetUser->id;
            try { $targetUser->update(['role' => $inv->role]); } catch (\Throwable) {}
        }

        // 3) Sikre kortkode + kortlenke, regenerer SMS-tekst med kort lenke.
        $shortCode = $inv->short_code ?: PortalThread::generateUniqueInviteCode();
        $shortLink = PortalThread::issuer() . '/i/' . $shortCode;
        $roleLabel = InviteEvents::roleLabel($inv->role);
        $smsBody = "Appendix Properties: Du er invitert som {$roleLabel}. Bekreft med BankID her: {$shortLink}";

        // 4) Marker som "sent" (forhåndsprovisjonert/godkjent). SMS sendes IKKE her.
        $inv->update([
            'status' => 'sent',
            'short_code' => $shortCode,
            'sms_body' => $smsBody,
            'target_user_id' => $targetUserId,
            'approved_at' => $now,
            'approved_by_id' => $user->id,
            'registration_sent_at' => $now,
        ]);

        InviteEvents::audit([
            'event_type' => 'user_invite_provisioned',
            'actor_user_id' => $user->id,
            'actor_email' => $user->email,
            'detail' => "pre-provisioned {$inv->email} ({$inv->role}); SMS pending manual approval",
            'success' => true,
        ]);

        return [
            'success' => true,
            'invite_id' => $inv->id,
            'target_user_id' => $targetUserId,
            'accept_link' => $shortLink,
            'sms_body' => $smsBody,
            'phone' => $inv->phone ?: '',
            'email' => $inv->email,
            'name' => $inv->name ?: '',
            'role' => $inv->role,
            'invite_error' => $createError,
            'note' => 'Bruker forhåndsprovisjonert (ingen plattform-epost sendt). SMS sendes manuelt etter godkjenning.',
        ];
    }
}
