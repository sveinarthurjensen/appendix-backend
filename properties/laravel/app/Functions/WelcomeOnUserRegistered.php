<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteEvents;
use App\Services\PortalThread;
use Illuminate\Support\Facades\Mail;

/**
 * Portert fra base44/functions/welcomeOnUserRegistered/entry.ts
 *
 * Planlagt jobb (Base44-workflow hvert 10. minutt): for sendte invitasjoner der brukeren nå
 * finnes, men velkomst-e-post (med BankID-lenke /accept-invite?token=…) ikke er sendt, send den.
 * Dekker spesielt inviterte uten mobil (SMS gikk ikke ved godkjenning).
 * Svar: {success: true, welcome_emails_sent}.
 *
 * SCHEDULING i Laravel (routes/console.php, Laravel 11+):
 *
 *   use Illuminate\Support\Facades\Schedule;
 *   Schedule::call(fn () => app(\App\Functions\WelcomeOnUserRegistered::class)(null, []))
 *       ->everyTenMinutes()
 *       ->name('welcome-on-user-registered')
 *       ->withoutOverlapping();
 *
 * (eller i app/Console/Kernel.php → schedule() på eldre versjoner). Krever at `php artisan
 * schedule:run` kjøres fra cron hvert minutt, eller `schedule:work` som tjeneste.
 * Kan også kalles manuelt via funksjonsruteren (ingen innloggingskrav – kjør den kun fra
 * scheduler/intern token, ikke eksponer offentlig).
 *
 * Avvik: Core.SendEmail med from_name "Appendix Properties" → Laravel Mail (avsender fra mail.from).
 */
class WelcomeOnUserRegistered extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $invites = UserInvite::base44Filter(['status' => 'sent'])->get();
        $sent = 0;

        foreach ($invites as $inv) {
            if ($inv->used_at || $inv->welcome_email_sent_at) {
                continue;
            }
            if (InviteEvents::isExpired($inv->expires_at)) {
                continue;
            }
            $email = strtolower(trim((string) $inv->email));
            if ($email === '') {
                continue;
            }
            $u = User::where('email', $email)->first();
            if (!$u) {
                continue; // ennå ikke registrert
            }
            // Påfør invitasjonens rolle.
            if ($inv->role && $u->role !== $inv->role) {
                try { $u->update(['role' => $inv->role]); } catch (\Throwable) {}
            }
            // Bind target_user_id nå som kontoen finnes.
            try { $inv->update(['target_user_id' => $u->id]); } catch (\Throwable) {}

            $acceptLink = PortalThread::issuer() . '/accept-invite?token=' . rawurlencode((string) $inv->token);
            $roleLabel = InviteEvents::roleLabel($inv->role);
            $e = fn ($v) => PortalThread::escapeHtml($v);
            $expires = '';
            try {
                $expires = $inv->expires_at ? \Carbon\Carbon::parse($inv->expires_at)->format('d.m.Y, H:i:s') : '';
            } catch (\Throwable) {}

            $body = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#1e293b;max-width:600px;margin:0 auto;padding:20px">'
                . '<h2 style="color:#1e3a5f">Velkommen til Appendix Properties, ' . $e($inv->name ?: ($u->full_name ?: '')) . '!</h2>'
                . '<p>Din konto er opprettet. For å få tilgang må du bekrefte identiteten din med BankID (én gang):</p>'
                . '<p style="margin:24px 0;text-align:center">'
                . '<a href="' . $acceptLink . '" style="background:#1e3a5f;color:#fff;padding:14px 28px;text-decoration:none;border-radius:8px;font-weight:bold">Bekreft identitet med BankID</a>'
                . '</p>'
                . '<p style="font-size:13px;color:#64748b">Rolle: ' . $e($roleLabel) . '. Lenken er personlig og utløper ' . $e($expires) . '.</p>'
                . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">'
                . '<p style="font-size:12px;color:#94a3b8">Appendix Properties — aprop.no</p>'
                . '</body></html>';

            try {
                Mail::to($email)->send(new PlainMail('Velkommen til Appendix Properties — bekreft med BankID', $body));
                $inv->update(['welcome_email_sent_at' => now(), 'target_user_id' => $u->id]);
                InviteEvents::audit([
                    'event_type' => 'user_invite_welcome_sent',
                    'actor_email' => $email,
                    'detail' => "scheduled welcome for {$email}",
                    'success' => true,
                ]);
                $sent++;
            } catch (\Throwable $ex) {
                InviteEvents::audit([
                    'event_type' => 'user_invite_welcome_sent',
                    'actor_email' => $email,
                    'success' => false,
                    'detail' => 'scheduled welcome failed: ' . ($ex->getMessage() ?: (string) $ex),
                ]);
            }
        }

        return ['success' => true, 'welcome_emails_sent' => $sent];
    }
}
