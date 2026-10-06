<?php

namespace App\Functions;

use App\Models\User;
use App\Models\UserInvite;
use App\Services\IdentityMatching;
use App\Services\InviteEvents;
use App\Services\PortalThread;

/**
 * Portert fra base44/functions/createUserInvite/entry.ts
 *
 * Admin oppretter et UserInvite-utkast (status 'draft') med token, kortkode (/i/<kode>),
 * ferdig e-post- og SMS-tekst. Ingen utsendelse her – det skjer i ProvisionUserInvite /
 * ApproveAndSendUserInvite. Svar: {success: true, invite}.
 *
 * Avvik:
 *  - hashFnr() (oidcProvider.ts) = usaltet SHA-256 av normalisert fnr → IdentityMatching::hashNin (identisk).
 *  - Feltene send_email/send_sms finnes ikke i UserInvite::$fillable og droppes (se rapport).
 *  - OIDC_ISSUER → config('services.portal.issuer') via PortalThread::issuer().
 */
class CreateUserInvite extends Base44Function
{
    private const INVITE_TTL_DAYS = 7;
    private const ROLE_VALUES = ['admin', 'arbeider', 'leietaker'];

    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan opprette invitasjoner', 403);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $role = $payload['role'] ?? 'leietaker';
        $nin = IdentityMatching::normalizeNationalId((string) ($payload['nin'] ?? ''));

        // SMS er eneste utsendelseskanal (KUN SMS) — mobil er påkrevd.
        if ($phone === '') {
            throw new FunctionException('Mobil er påkrevd (SMS er utsendelseskanal)', 400);
        }
        // E-post brukes som kontoident for BankID/SSO auto-innlogging.
        if ($email === '' || !str_contains($email, '@')) {
            throw new FunctionException('E-post er påkrevd (brukes som kontoident for BankID-innlogging)', 400);
        }
        if (!in_array($role, self::ROLE_VALUES, true)) {
            throw new FunctionException('Ugyldig rolle', 400);
        }

        $ninHash = '';
        if ($nin !== '') {
            if (!preg_match('/^\d{11}$/', $nin)) {
                throw new FunctionException('Fødselsnummer må være 11 siffer', 400);
            }
            $ninHash = IdentityMatching::hashNin($nin);
        }

        $token = PortalThread::rand(24);
        $shortCode = PortalThread::generateUniqueInviteCode();
        $now = now();
        $expiresAt = $now->copy()->addDays(self::INVITE_TTL_DAYS);
        $shortLink = PortalThread::issuer() . '/i/' . $shortCode;

        $roleLabel = InviteEvents::roleLabel($role);
        $e = fn ($v) => PortalThread::escapeHtml($v);

        $emailSubject = "Invitasjon til Appendix Properties — {$roleLabel}";
        $emailBody = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1e293b;max-width:600px;margin:0 auto;padding:20px">'
            . '<h2 style="color:#1e3a5f">Hei ' . $e($name) . ',</h2>'
            . '<p>Du er invitert til <strong>Appendix Properties</strong> som <strong>' . $e($roleLabel) . '</strong>.</p>'
            . '<p>For å få tilgang må du bekrefte identiteten din med BankID:</p>'
            . '<p style="margin:24px 0;text-align:center">'
            . '<a href="' . $shortLink . '" style="background:#1e3a5f;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;font-weight:bold">Bekreft med BankID</a>'
            . '</p>'
            . '<p style="font-size:13px;color:#64748b">Lenken er personlig og utløper ' . $e($expiresAt->format('d.m.Y, H:i:s')) . '. Hvis du ikke forventet denne invitasjonen, kan du se bort fra meldingen.</p>'
            . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">'
            . '<p style="font-size:12px;color:#94a3b8">Sendt fra Appendix Properties — aprop.no</p>'
            . '</body></html>';

        $smsBody = "Appendix Properties: Du er invitert som {$roleLabel}. Bekreft med BankID her: {$shortLink}";

        $invite = UserInvite::create([
            'token' => $token,
            'short_code' => $shortCode,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'nin_hash' => $ninHash,
            'status' => 'draft',
            'expires_at' => $expiresAt,
            'created_by_id' => $user->id,
            'created_by_name' => $user->email,
            'created_at' => $now,
            'email_subject' => $emailSubject,
            'email_body' => $emailBody,
            'sms_body' => $smsBody,
            // send_email: true, send_sms: !!phone — finnes ikke i UserInvite::$fillable; droppet.
        ]);

        InviteEvents::audit([
            'event_type' => 'user_invite_created',
            'actor_user_id' => $user->id,
            'actor_email' => $user->email,
            'detail' => "draft for {$email} ({$role})",
            'success' => true,
        ]);

        InviteEvents::log([
            'invite_id' => $invite->id,
            'event_type' => 'opprettet',
            'actor_name' => $user->email,
            'actor_email' => $user->email,
            'invite_email' => $email,
            'detail' => "Utkast opprettet som {$role}",
        ]);

        return ['success' => true, 'invite' => $invite->toBase44Array()];
    }
}
