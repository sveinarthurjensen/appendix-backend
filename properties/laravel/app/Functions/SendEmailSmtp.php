<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Portert fra base44/functions/sendEmailSmtp/entry.ts
 *
 * Admin sender e-post. Originalen sjekket SMTP_HOST/SMTP_USERNAME/SMTP_PASSWORD
 * og brukte deretter Core.SendEmail; her brukes Laravels standard mailer
 * (config/mail.php, mailer «smtp»), og de samme nøklene leses derfra.
 * Svar: {success: true, result}
 */
class SendEmailSmtp extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin kan sende e-post', 403);
        }

        $to = $payload['to'] ?? null;
        $subject = $payload['subject'] ?? null;
        $body = $payload['body'] ?? null;
        $replyTo = $payload['replyTo'] ?? null;

        if (!$to || !$subject || !$body) {
            throw new FunctionException('Mangler to, subject eller body', 400);
        }

        $smtpHost = config('mail.mailers.smtp.host');
        $smtpUser = config('mail.mailers.smtp.username');
        $smtpPass = config('mail.mailers.smtp.password');
        if (!$smtpHost || !$smtpUser || !$smtpPass) {
            throw new FunctionException('SMTP ikke konfigurert. Sett SMTP_HOST, SMTP_USERNAME og SMTP_PASSWORD.', 500);
        }

        Mail::to($to)->send(new PlainMail((string) $subject, (string) $body, $replyTo ?: $smtpUser));

        // Logg til ChatMessage (som originalen)
        ChatMessage::create([
            'session_id' => 'email-' . (int) (microtime(true) * 1000),
            'sender_name' => 'Admin',
            'sender_email' => $smtpUser,
            'message' => "E-post sendt til {$to}: {$subject}",
            'direction' => 'utgående',
            'channel' => 'email',
        ]);

        // Base44 Core.SendEmail returnerte et integrasjonsobjekt; Laravel Mail returnerer ingenting.
        return ['success' => true, 'result' => ['to' => $to, 'subject' => $subject, 'sent' => true]];
    }
}
