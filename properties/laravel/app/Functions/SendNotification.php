<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Portert fra base44/functions/sendNotification/entry.ts
 *
 * Sender e-post (og «logger» SMS som pending – originalen hadde ingen SMS-leverandør
 * koblet på her) basert på NotificationTemplate eller custom_data, og skriver NotificationLog.
 * Svar: {success: true, results: [{channel, status, error?, note?}]}
 */
class SendNotification extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireUser($user);

        $templateId = $payload['template_id'] ?? null;
        $recipientId = $payload['recipient_id'] ?? null;
        $recipientEmail = $payload['recipient_email'] ?? null;
        $recipientPhone = $payload['recipient_phone'] ?? null;
        $channel = $payload['channel'] ?? null;
        $customData = is_array($payload['custom_data'] ?? null) ? $payload['custom_data'] : [];

        $template = null;
        if ($templateId) {
            $template = NotificationTemplate::base44Filter(['id' => $templateId])->first();
            if (!$template || !$template->is_active) {
                throw new FunctionException('Template not found or inactive', 404);
            }
        }

        $replace = function (?string $text, array $data): string {
            if (!$text) {
                return '';
            }
            foreach (['name', 'property', 'amount', 'date', 'link', 'message'] as $k) {
                $text = str_replace('{{' . $k . '}}', (string) ($data[$k] ?? ''), $text);
            }
            return $text;
        };

        $results = [];

        // E-post
        if (($channel === 'email' || $channel === 'both') && $recipientEmail) {
            $subject = $replace($template?->subject ?: ($customData['subject'] ?? 'Melding fra Appendix Properties'), $customData);
            $emailBody = $replace($template?->email_body ?: ($customData['email_body'] ?? ''), $customData);

            $log = [
                'template_id' => $templateId,
                'recipient_id' => $recipientId,
                'recipient_email' => $recipientEmail,
                'channel' => 'email',
                'subject' => $subject,
                'body' => $emailBody,
                'sent_date' => now(),
                'metadata' => $customData,
            ];
            try {
                Mail::to($recipientEmail)->send(new PlainMail($subject, $emailBody));
                NotificationLog::create($log + ['status' => 'sent']);
                $results[] = ['channel' => 'email', 'status' => 'sent'];
            } catch (\Throwable $e) {
                NotificationLog::create($log + ['status' => 'failed', 'error_message' => $e->getMessage()]);
                $results[] = ['channel' => 'email', 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        // SMS – originalen hadde kun en placeholder (ingen leverandør), beholdes 1:1.
        if (($channel === 'sms' || $channel === 'both') && $recipientPhone) {
            $smsBody = $replace($template?->sms_body ?: ($customData['sms_body'] ?? ''), $customData);

            NotificationLog::create([
                'template_id' => $templateId,
                'recipient_id' => $recipientId,
                'recipient_phone' => $recipientPhone,
                'channel' => 'sms',
                'body' => $smsBody,
                'status' => 'pending',
                'sent_date' => now(),
                'metadata' => $customData + ['note' => 'SMS provider not configured'],
            ]);

            $results[] = ['channel' => 'sms', 'status' => 'pending', 'note' => 'SMS provider not configured'];
        }

        return ['success' => true, 'results' => $results];
    }
}
