<?php

namespace App\Functions;

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\SveveSms;

/**
 * Portert fra base44/functions/sendUserMessage/entry.ts
 *
 * Admin-funksjon «Send melding til bruker»: SMS via Sveve til hver bruker i user_ids,
 * og én NotificationLog-rad per utsending (også for feil).
 * Svar: {success: true, results: [{user_id, email?, name?, phone?, status, error}]}
 */
class SendUserMessage extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $actor = $this->requireAdmin($user);

        $userIds = $payload['user_ids'] ?? null;
        $body = trim((string) ($payload['text'] ?? $payload['message'] ?? ''));
        $senderName = $payload['sender'] ?? $payload['from'] ?? 'Appendix';

        if (!is_array($userIds) || count($userIds) === 0) {
            throw new FunctionException('Mangler user_ids (velg minst én mottaker)', 400);
        }
        if ($body === '') {
            throw new FunctionException('Mangler meldingstekst', 400);
        }

        $sms = app(SveveSms::class);
        $results = [];

        foreach ($userIds as $uid) {
            $u = User::find($uid);
            if (!$u) {
                NotificationLog::create([
                    'recipient_id' => $uid,
                    'recipient_email' => '',
                    'recipient_phone' => '',
                    'channel' => 'sms',
                    'body' => $body,
                    'status' => 'failed',
                    'sent_date' => now(),
                    'error_message' => 'Bruker ikke funnet',
                    'metadata' => ['actor' => $actor->email],
                ]);
                $results[] = ['user_id' => $uid, 'status' => 'failed', 'error' => 'Bruker ikke funnet'];
                continue;
            }

            $name = $u->full_name ?: $u->email;
            // Originalen leste u.mobile || u.phone; User-modellen har bare `mobile`.
            $mobile = trim((string) ($u->mobile ?? ''));

            if ($mobile === '') {
                NotificationLog::create([
                    'recipient_id' => $uid,
                    'recipient_email' => $u->email ?? '',
                    'recipient_phone' => '',
                    'channel' => 'sms',
                    'body' => $body,
                    'status' => 'failed',
                    'sent_date' => now(),
                    'error_message' => 'Brukeren har ikke mobilnummer lagret',
                    'metadata' => ['actor' => $actor->email],
                ]);
                $results[] = [
                    'user_id' => $uid,
                    'email' => $u->email,
                    'name' => $name,
                    'status' => 'failed',
                    'error' => 'Brukeren har ikke mobilnummer lagret',
                ];
                continue;
            }

            $r = $sms->send($mobile, $body, (string) $senderName);
            NotificationLog::create([
                'recipient_id' => $uid,
                'recipient_email' => $u->email ?? '',
                'recipient_phone' => $mobile,
                'channel' => 'sms',
                'body' => $body,
                'status' => $r['ok'] ? 'sent' : 'failed',
                'sent_date' => now(),
                'error_message' => $r['ok'] ? '' : ($r['error'] ?? ''),
                'metadata' => [
                    'actor' => $actor->email,
                    'sveve' => $r['raw'] ?? ['msgOkCount' => $r['msgOkCount'] ?? null],
                ],
            ]);
            $results[] = [
                'user_id' => $uid,
                'email' => $u->email,
                'name' => $name,
                'phone' => $mobile,
                'status' => $r['ok'] ? 'sent' : 'failed',
                'error' => $r['ok'] ? null : ($r['error'] ?? null),
            ];
        }

        return ['success' => true, 'results' => $results];
    }
}
