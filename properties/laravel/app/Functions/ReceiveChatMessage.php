<?php

namespace App\Functions;

use App\Models\ChatMessage;
use App\Models\User;

/**
 * Portert fra base44/functions/receiveChatMessage/entry.ts
 *
 * Offentlig endepunkt (ingen auth): lagrer innkommende chat-melding.
 * Svar: {success: true, id}
 */
class ReceiveChatMessage extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $sessionId = $payload['session_id'] ?? null;
        $message = $payload['message'] ?? null;

        if (!$sessionId || !$message) {
            throw new FunctionException('Mangler session_id eller message', 400);
        }

        $saved = ChatMessage::create([
            'session_id' => $sessionId,
            'sender_name' => ($payload['sender_name'] ?? null) ?: 'Anonym',
            'sender_email' => $payload['sender_email'] ?? '',
            'sender_phone' => $payload['sender_phone'] ?? '',
            'message' => $message,
            'direction' => 'innkommende',
            'channel' => 'chat',
            'status' => 'ulest',
            'property_interest' => $payload['property_interest'] ?? '',
        ]);

        return ['success' => true, 'id' => $saved->id];
    }
}
