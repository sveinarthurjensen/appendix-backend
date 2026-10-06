<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Sveve SMS – erstatter base44/shared/sveveSms.ts. Konfig: services.sveve.username/password */
class SveveSms
{
    /** @return array{ok: bool, msgOkCount?: int, error?: string, raw?: mixed} */
    public function send(string $to, string $text, ?string $sender = null): array
    {
        $user = config('services.sveve.username');
        $pass = config('services.sveve.password');
        if (!$user || !$pass) {
            return ['ok' => false, 'error' => 'Sveve ikke konfigurert (SVEVE_USERNAME / SVEVE_PASSWORD)'];
        }
        try {
            $res = Http::asForm()->timeout(20)->post('https://sveve.no/SMS/SendMessage', [
                'user' => $user, 'passwd' => $pass,
                'to' => preg_replace('/\s+/', '', $to),
                'from' => $sender ?: 'Appendix',
                'msg' => $text, 'f' => 'json',
            ]);
            $parsed = $res->json();
            if (($parsed['response']['msgOkCount'] ?? 0) > 0) {
                return ['ok' => true, 'msgOkCount' => $parsed['response']['msgOkCount']];
            }
            return ['ok' => false, 'error' => $parsed['response']['errors'][0]['message'] ?? 'Sveve-feil', 'raw' => $parsed];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage() ?: 'Sveve-nettverksfeil'];
        }
    }
}
