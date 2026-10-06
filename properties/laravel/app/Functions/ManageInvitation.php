<?php

namespace App\Functions;

use App\Models\InboxMessage;
use App\Models\Invitation;
use App\Models\User;
use App\Services\IdentityMatching;
use App\Services\InviteEvents;
use App\Services\PortalThread;
use App\Services\SveveSms;

/**
 * Portert fra base44/functions/manageInvitation/entry.ts
 *
 * Administrer Invitation-entiteten (Vei 1/3) — admin-only.
 *  - action "create" : opprett Invitation-utkast + token (ingen utsendelse).
 *  - action "send"   : send SMS med /register-lenke, sett status='sent'.
 *  - action "revoke" : tilbakekall invitasjon (status='expired').
 *
 * Avvik: APP_BASE_URL → config('services.portal.issuer') (samme offentlige domene, aprop.no).
 */
class ManageInvitation extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan administrere invitasjoner', 403);
        }

        $action = $payload['action'] ?? null;
        $appBase = PortalThread::issuer();

        return match ($action) {
            'create' => $this->create($user, $payload),
            'send' => $this->send($user, $payload, $appBase),
            'revoke' => $this->revoke($user, $payload),
            default => throw new FunctionException('Ukjent action', 400),
        };
    }

    private function audit(string $eventType, string $detail, string $actorEmail = ''): void
    {
        InviteEvents::audit(['event_type' => $eventType, 'actor_email' => $actorEmail, 'detail' => $detail, 'success' => true]);
    }

    private function create(User $user, array $b): array
    {
        $email = $b['email'] ?? null;
        $mobile = $b['mobile'] ?? null;
        if (!$email) {
            throw new FunctionException('E-post er påkrevd', 400);
        }
        if (!$mobile) {
            throw new FunctionException('Mobil er påkrevd (SMS sendes til mobil)', 400);
        }

        $token = bin2hex(random_bytes(24));

        $nationalIdHash = '';
        $nidLast4 = '';
        if (!empty($b['national_id'])) {
            $nationalIdHash = IdentityMatching::hashNin((string) $b['national_id']);
            $nidLast4 = IdentityMatching::nationalIdLast4((string) $b['national_id']);
        }

        $role = $b['role'] ?? 'leietaker';
        $inv = Invitation::create([
            'email' => $email,
            'full_name' => $b['full_name'] ?? '',
            'mobile' => $mobile,
            'role' => $role,
            'niva' => ($b['niva'] ?? null) == 2 ? 2 : 1,
            'national_id_hash' => $nationalIdHash,
            'national_id_last4' => $nidLast4,
            'token' => $token,
            'status' => 'pending',
            'invited_by' => $user->id,
            'invited_at' => now(),
            'send_email' => (bool) ($b['send_email'] ?? false),
            'send_sms' => ($b['send_sms'] ?? null) !== false,
        ]);

        $this->audit('invite_opprettet', "Opprettet — {$email} ({$role})", $user->email);

        return ['success' => true, 'invitation_id' => $inv->id, 'token' => $token];
    }

    private function send(User $user, array $b, string $appBase): array
    {
        $invitationId = $b['invitation_id'] ?? null;
        if (!$invitationId) {
            throw new FunctionException('invitation_id kreves', 400);
        }
        $inv = Invitation::find($invitationId);
        if (!$inv) {
            throw new FunctionException('Invitasjon ikke funnet', 404);
        }
        if ($inv->status !== 'pending') {
            throw new FunctionException("Invitasjon har status {$inv->status}", 400);
        }

        $registerLink = $appBase . '/register?email=' . rawurlencode((string) $inv->email) . '&invite=' . rawurlencode((string) $inv->token);
        $roleLabel = InviteEvents::roleLabel($inv->role);
        $smsBody = "Appendix Properties: Du er invitert som {$roleLabel}. Opprett konto og bekreft med BankID her: {$registerLink}";

        $smsOk = false;
        $smsError = '';
        if ($inv->send_sms !== false) {
            if (!config('services.sveve.username') || !config('services.sveve.password')) {
                $smsError = 'Sveve ikke konfigurert';
            } else {
                $r = app(SveveSms::class)->send((string) $inv->mobile, $smsBody, 'Appendix');
                $smsOk = (bool) ($r['ok'] ?? false);
                if (!$smsOk) {
                    $smsError = $r['error'] ?? 'Sveve-feil';
                }
            }
            if ($smsOk) {
                try {
                    InboxMessage::create([
                        'message_type' => 'sms',
                        'direction' => 'outgoing',
                        'from_address' => 'Appendix',
                        'to_address' => $inv->mobile,
                        'subject' => '',
                        'body_text' => $smsBody,
                        'mailbox' => '',
                        'sent_date' => now(),
                        'sent_by' => $user->full_name ?: $user->email,
                        'status' => 'ny',
                    ]);
                } catch (\Throwable) {}
            }
        }

        if (!$smsOk && $inv->send_sms !== false) {
            throw new FunctionException("SMS kunne ikke sendes: {$smsError}", 400);
        }

        $inv->update(['status' => 'sent', 'sent_at' => now()]);
        $this->audit('invite_sendt', "SMS med registreringslenke sendt — {$inv->email}", $user->email);

        return ['success' => true, 'register_link' => $registerLink, 'sms_sent' => $smsOk];
    }

    private function revoke(User $user, array $b): array
    {
        $invitationId = $b['invitation_id'] ?? null;
        if (!$invitationId) {
            throw new FunctionException('invitation_id kreves', 400);
        }
        $inv = Invitation::find($invitationId);
        if (!$inv) {
            throw new FunctionException('Invitasjon ikke funnet', 404);
        }
        if ($inv->status === 'expired') {
            return ['success' => true, 'already_expired' => true];
        }

        $inv->update(['status' => 'expired']);
        $this->audit('invite_tilbakekalt', "Tilbakekalt av administrator — {$inv->email}", $user->email);

        return ['success' => true];
    }
}
