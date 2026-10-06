<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteEvents;
use App\Services\SveveSms;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Portert fra base44/functions/approveAndSendUserInvite/entry.ts
 *
 * Admin godkjenner et utkast: registrerer bruker, sender SMS med BankID-lenke (Sveve) og
 * setter status 'sent'. Svar: {success, target_user_id, sms_sent, sms_error, invite_error, note}.
 *
 * Avvik (brukeropprettelse): originalen kalte base44.users.inviteUser(email, 'admin'|'user'),
 * som fikk Base44-plattformen til å sende registrerings-e-post. Her finnes ingen plattform:
 * App\Models\User opprettes direkte (id = ulid, app_id = 'appendix_properties', role fra
 * invitasjonen, password = null – innlogging via BankID/OIDC), og invitasjonens egen e-post
 * (email_subject/email_body med BankID-lenke) sendes via Laravel Mail i stedet for
 * plattform-e-posten. Feil ved opprettelse/e-post er ikke fatal (invite_error), som i originalen.
 *  - SMS: originalen brukte GET mot Sveve og sjekket "OK"-prefiks; her brukes SveveSms (JSON-svar).
 */
class ApproveAndSendUserInvite extends Base44Function
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

        $now = now();
        $targetUserId = '';
        $inviteError = '';

        // 1) Registrer bruker (erstatter base44.users.inviteUser) + send invitasjons-e-post.
        $targetUser = $inv->email ? User::where('email', $inv->email)->first() : null;
        if (!$targetUser && $inv->email) {
            try {
                $targetUser = User::create([
                    'id' => strtolower((string) Str::ulid()),
                    'app_id' => 'appendix_properties',
                    'email' => $inv->email,
                    'full_name' => $inv->name ?: '',
                    'role' => $inv->role,
                    'password' => null,
                ]);
            } catch (\Throwable $e) {
                $inviteError = $e->getMessage() ?: (string) $e;
            }
        }
        if ($inv->email && $inv->email_body) {
            try {
                Mail::to($inv->email)->send(new PlainMail(
                    $inv->email_subject ?: 'Invitasjon til Appendix Properties',
                    $inv->email_body
                ));
            } catch (\Throwable $e) {
                $inviteError = trim($inviteError . ' ' . ($e->getMessage() ?: (string) $e));
            }
        }

        // 2) Sett valgt rolle på (evt. allerede eksisterende) bruker.
        if ($targetUser) {
            $targetUserId = $targetUser->id;
            try { $targetUser->update(['role' => $inv->role]); } catch (\Throwable) {}
        }

        // 3) App-egen SMS med BankID-lenke (Sveve) hvis mobil oppgitt.
        $smsSent = false;
        $smsError = '';
        if ($inv->phone) {
            if (config('services.sveve.username') && config('services.sveve.password')) {
                $r = app(SveveSms::class)->send((string) $inv->phone, (string) $inv->sms_body, 'Appendix');
                $smsSent = (bool) ($r['ok'] ?? false);
                if (!$smsSent) {
                    $smsError = $r['error'] ?? 'Sveve-feil';
                }
            } else {
                $smsError = 'Sveve ikke konfigurert';
            }
        }

        $inv->update([
            'status' => 'sent',
            'target_user_id' => $targetUserId,
            'approved_at' => $now,
            'approved_by_id' => $user->id,
            'registration_sent_at' => $now,
            'sms_sent_at' => $smsSent ? $now : null,
        ]);

        InviteEvents::audit([
            'event_type' => 'user_invite_sent',
            'actor_user_id' => $user->id,
            'actor_email' => $user->email,
            'detail' => "to {$inv->email} ({$inv->role}) sms=" . ($smsSent ? 'true' : 'false'),
            'success' => true,
        ]);

        return [
            'success' => true,
            'target_user_id' => $targetUserId,
            'sms_sent' => $smsSent,
            'sms_error' => $smsError,
            'invite_error' => $inviteError,
            'note' => 'Invitasjons-e-post sendt. Velkomst-e-post med BankID-lenke sendes automatisk når brukeren er registrert.',
        ];
    }
}
