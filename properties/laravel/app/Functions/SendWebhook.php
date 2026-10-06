<?php

namespace App\Functions;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/sendWebhook/entry.ts
 *
 * Innlogget bruker sender {event, data} til WEBHOOK_URL (services.webhook.url).
 * Svar: {success: true, message, response} eller {success: false, error, details} (500).
 */
class SendWebhook extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $user = $this->requireUser($user);

        $webhookUrl = config('services.webhook.url');
        if (!$webhookUrl) {
            throw new FunctionException('Webhook URL not configured', 500);
        }

        $body = [
            'event' => $payload['event'] ?? 'custom_event',
            'timestamp' => now()->toISOString(),
            'source' => 'appendix_properties',
            'user' => [
                'email' => $user->email,
                'name' => $user->full_name,
            ],
            'data' => $payload['data'] ?? (object) [],
        ];

        try {
            $response = Http::timeout(30)->asJson()->post($webhookUrl, $body);
        } catch (\Throwable $e) {
            throw new FunctionException('Webhook failed: ' . $e->getMessage(), 500, ['success' => false, 'details' => '']);
        }

        if (!$response->successful()) {
            throw new FunctionException("Webhook failed: {$response->status()}", 500, [
                'success' => false,
                'details' => $response->body(),
            ]);
        }

        $responseData = $response->json();
        if ($responseData === null) {
            $responseData = $response->body();
        }

        return [
            'success' => true,
            'message' => 'Webhook sent successfully',
            'response' => $responseData,
        ];
    }
}
