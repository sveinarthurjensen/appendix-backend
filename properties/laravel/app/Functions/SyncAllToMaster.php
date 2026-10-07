<?php

namespace App\Functions;

use App\Models\SecureCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/syncAllToMaster/entry.ts
 *
 * Planlagt jobb (workflow «Daglig synk til master hub», cron 30 23 * * * UTC): synker
 * SecureCredential (passord) og brukere til master-huben (Appendix Holding) med
 * x-master-sync-token. URL-er og token i config services.holding.master_* – peker på Base44
 * inntil Holding-appen er flyttet.
 *
 * Avvik: originalen krevde innlogget admin (og feilet derfor med 401 ved planlagt kjøring).
 * Her tillates planlagt kjøring (ingen bruker) ELLER admin.
 * Svar: {success, source_app, result: {credentials: {synced, skipped, error?, hub_response?}, users: {...}}}
 */
class SyncAllToMaster extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if ($user && !$user->hasRole('admin')) {
            throw new FunctionException('Forbidden', 403);
        }

        $syncToken = config('services.holding.master_sync_token');
        if (!$syncToken) {
            throw new FunctionException('MASTER_SYNC_TOKEN must be set', 500);
        }

        $appId = config('services.holding.app_id') ?: 'unknown';
        $credsUrl = config('services.holding.master_hub_url');
        $usersUrl = config('services.holding.master_hub_url_users');

        $result = [
            'credentials' => ['synced' => 0, 'skipped' => !$credsUrl],
            'users' => ['synced' => 0, 'skipped' => !$usersUrl],
        ];

        // 1. Passord-synk
        if ($credsUrl) {
            $credPayload = SecureCredential::base44Filter(['is_sync_token' => false])->get()->map(fn ($c) => [
                'name' => $c->name,
                'company_id' => $c->company_id ?: '',
                'category' => $c->category ?: 'annet',
                'username' => $c->username ?: '',
                'encrypted_password' => $c->encrypted_password ?: '',
                'url' => $c->url ?: '',
                'two_factor_enabled' => (bool) $c->two_factor_enabled,
                'two_factor_method' => $c->two_factor_method ?: '',
                'recovery_codes' => $c->recovery_codes ?: '',
                'notes' => $c->notes ?: '',
                'access_level' => $c->access_level ?: 'admin_only',
            ])->values()->all();

            [$ok, $body] = $this->post($credsUrl, $syncToken, [
                'source_app_id' => $appId, 'source_app_name' => $appId, 'credentials' => $credPayload,
            ]);
            if (!$ok) {
                $result['credentials']['error'] = $body;
            } else {
                $result['credentials']['synced'] = count($credPayload);
                $result['credentials']['hub_response'] = $body;
            }
        }

        // 2. Bruker-synk
        if ($usersUrl) {
            $userPayload = User::whereNotNull('email')->where('email', '!=', '')->get()->map(function (User $u) {
                $email = strtolower(trim((string) $u->email));
                return [
                    'email' => $email,
                    'display_name' => $u->display_name ?: ($u->full_name ?: ''),
                    'full_name' => $u->full_name ?: '',
                    'title' => $u->title ?: '',
                    'department' => $u->department ?: '',
                    'phone' => $u->phone ?: '',
                    'mobile_phone' => $u->mobile_phone ?: ($u->mobile ?: ''),
                    'signature_domain' => $u->signature_domain ?: (str_contains($email, '@') ? explode('@', $email, 2)[1] : ''),
                    'role' => $u->role ?: '',
                    'can_sign' => (bool) $u->can_sign,
                    'signing_authority' => $u->signing_authority ?: 'ingen',
                ];
            })->values()->all();

            [$ok, $body] = $this->post($usersUrl, $syncToken, [
                'source_app_id' => $appId, 'source_app_name' => $appId, 'users' => $userPayload,
            ]);
            if (!$ok) {
                $result['users']['error'] = $body;
            } else {
                $result['users']['synced'] = count($userPayload);
                $result['users']['hub_response'] = $body;
            }
        }

        return ['success' => true, 'source_app' => $appId, 'result' => $result];
    }

    /** @return array{0: bool, 1: mixed} [ok, dekodet JSON eller råtekst] */
    private function post(string $url, string $token, array $payload): array
    {
        try {
            $res = Http::timeout(60)
                ->withHeaders(['x-master-sync-token' => $token])
                ->asJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
        $text = $res->body();
        $decoded = json_decode($text, true);
        $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $text;
        return [$res->successful(), $body];
    }
}
