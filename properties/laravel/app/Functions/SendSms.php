<?php

namespace App\Functions;

use App\Models\InboxMessage;
use App\Models\User;
use App\Services\SveveSms;

/**
 * Portert fra base44/functions/sendSms/entry.ts
 *
 * Admin sender én SMS via Sveve og logger den som utgående InboxMessage.
 * Svar: {success: true, msgOkCount} eller {success: false, error, raw} (400).
 */
class SendSms extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $user = $this->requireAdmin($user);

        $to = $payload['to'] ?? null;
        $text = $payload['msg'] ?? $payload['message'] ?? null;
        $senderName = $payload['from'] ?? $payload['sender'] ?? 'Appendix';

        if (!$to || !$text) {
            throw new FunctionException('Mangler mottaker (to) eller melding (msg)', 400);
        }

        if (!config('services.sveve.username') || !config('services.sveve.password')) {
            throw new FunctionException('Sveve ikke konfigurert (SVEVE_USERNAME / SVEVE_PASSWORD)', 500);
        }

        // SveveSms fjerner selv mellomrom i nummeret (Sveve avviser numre med mellomrom).
        $r = app(SveveSms::class)->send((string) $to, (string) $text, (string) $senderName);

        if ($r['ok']) {
            InboxMessage::create([
                'message_type' => 'sms',
                'direction' => 'outgoing',
                'from_address' => $senderName,
                'to_address' => $to,
                'subject' => '',
                'body_text' => $text,
                'mailbox' => '',
                'sent_date' => now(),
                'sent_by' => $user->full_name ?: $user->email,
                'status' => 'ny',
            ]);
            return ['success' => true, 'msgOkCount' => $r['msgOkCount']];
        }

        throw new FunctionException($r['error'] ?? 'Sveve-feil', 400, [
            'success' => false,
            'raw' => $r['raw'] ?? null,
        ]);
    }
}
