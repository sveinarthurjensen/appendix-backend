<?php

namespace App\Services\Oidc;

use App\Models\AuditEvent;
use App\Models\OidcAuthFlow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OIDC-utsteder (Laravel som identity provider) – portert fra base44/shared/oidcProvider.ts og
 * funksjonene oidcDiscovery / oidcAuthorize / oidcToken / oidcUserinfo / oidcJwks.
 *
 * Flyt (authorization code + PKCE):
 *  1) Klient-app → GET /oidc/authorize?client_id&redirect_uri&state&nonce&code_challenge…
 *     → startAuthorize() lager en OidcAuthFlow (status pending) og brukeren sendes til innlogging
 *       (Entra / BankID / senere WebAuthn-QR). Er brukeren allerede innlogget i Laravel-sesjonen,
 *       fullføres flyten direkte (SSO).
 *  2) Innloggingen (EntraController m.fl.) kaller complete() → auth_code + redirect tilbake til klienten.
 *  3) Klient → POST /oidc/token (authorization_code + code_verifier) → id_token, access_token, refresh_token.
 *  4) Klient → GET /oidc/userinfo med Bearer access_token.
 *
 * Avvik fra Base44-versjonen:
 *  - Klienter må være registrert i config('services.oidc.clients') (Base44 godtok alle client_id/redirect_uri).
 *  - PKCE S256 håndheves når klienten sendte code_challenge; refresh_token-grant er nytt.
 *  - Signicat/QR-hurtigsti er ikke med her (BankID og WebAuthn/QR kommer i del 2/3).
 */
class Provider
{
    public const AUTH_CODE_TTL = 600;          // 10 min (AUTH_CODE_TTL_MS i originalen)
    public const ACCESS_TOKEN_TTL = 3600;      // 1 t
    public const REFRESH_TOKEN_TTL = 30 * 86400; // 30 d

    public function __construct(private readonly KeyStore $keys)
    {
    }

    // ---------- konfig ----------

    /** Utsteder-URL (iss-claim). OIDC_ISSUER deles med Min side-lenkene (services.portal.issuer). */
    public function issuer(): string
    {
        return rtrim((string) (config('services.oidc.issuer') ?: config('services.portal.issuer') ?: 'https://aprop.no'), '/');
    }

    /** @return array<string, array{name?: string, secret?: ?string, redirect_uris: string[]}> */
    public function clients(): array
    {
        $clients = (array) config('services.oidc.clients', []);
        foreach ($clients as $id => &$c) {
            $uris = $c['redirect_uris'] ?? [];
            if (is_string($uris)) {
                $uris = explode(',', $uris);
            }
            $c['redirect_uris'] = array_values(array_filter(array_map('trim', (array) $uris)));
        }
        return $clients;
    }

    public function client(string $clientId): ?array
    {
        return $this->clients()[$clientId] ?? null;
    }

    /** Discovery-dokument (/.well-known/openid-configuration). Endepunktene peker på de nye /oidc/-stiene. */
    public function discovery(): array
    {
        $i = $this->issuer();
        return [
            'issuer' => $i,
            'authorization_endpoint' => "$i/oidc/authorize",
            'token_endpoint' => "$i/oidc/token",
            'userinfo_endpoint' => "$i/oidc/userinfo",
            'jwks_uri' => "$i/oidc/jwks",
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email', 'offline_access'],
            'claims_supported' => ['sub', 'email', 'email_verified', 'name', 'role', 'app_id', 'identity_provider', 'bankid_verified', 'nin_level', 'nin_hash', 'acr', 'amr'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'code_challenge_methods_supported' => ['S256'],
        ];
    }

    public function jwks(): array
    {
        return $this->keys->jwks();
    }

    // ---------- authorize ----------

    /**
     * Valider authorize-forespørselen og opprett en ventende flyt.
     * Kaster OidcException ved ugyldig klient/redirect_uri (må IKKE redirectes – vises som feilside).
     */
    public function startAuthorize(Request $request): OidcAuthFlow
    {
        $clientId = (string) $request->query('client_id', '');
        $redirectUri = (string) $request->query('redirect_uri', '');
        $responseType = (string) $request->query('response_type', 'code');
        $scope = (string) $request->query('scope', 'openid profile email');
        $state = (string) $request->query('state', '');
        $nonce = (string) $request->query('nonce', '');
        $challenge = (string) $request->query('code_challenge', '');
        $method = (string) $request->query('code_challenge_method', '');

        if ($clientId === '' || $redirectUri === '') {
            throw new OidcException('invalid_request', 'client_id og redirect_uri er påkrevd');
        }
        $client = $this->client($clientId);
        if (!$client) {
            throw new OidcException('unauthorized_client', "Ukjent klient «$clientId»");
        }
        if (!in_array($redirectUri, $client['redirect_uris'], true)) {
            throw new OidcException('invalid_request', 'redirect_uri er ikke registrert for klienten');
        }
        if ($responseType !== 'code') {
            throw new OidcException('unsupported_response_type', 'Bare response_type=code støttes');
        }
        if ($challenge !== '') {
            if ($method === '') {
                $method = 'plain';
            }
            if ($method !== 'S256') {
                throw new OidcException('invalid_request', 'Bare code_challenge_method=S256 støttes');
            }
            if (!preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) {
                throw new OidcException('invalid_request', 'Ugyldig code_challenge');
            }
        } elseif (!empty($client['require_pkce'])) {
            throw new OidcException('invalid_request', 'Klienten krever PKCE (code_challenge mangler)');
        }

        $flow = OidcAuthFlow::create([
            'flow_id' => Jwt::rand(16),
            'auth_code' => Jwt::rand(24),
            'state' => $state,
            'nonce' => $nonce,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'response_type' => $responseType,
            'code_challenge' => $challenge,
            'code_challenge_method' => $challenge !== '' ? $method : '',
            'status' => 'pending',
            'source' => '',
            'amr' => [],
            'created_at' => now(),
            'expires_at' => now()->addSeconds(self::AUTH_CODE_TTL),
            'ip' => $request->ip(),
            // Invitasjonslenke: /oidc/authorize?...&invite_token=… → BankID-callback binder fnr til kontoen
            'invite_token' => substr((string) $request->query('invite_token', ''), 0, 128) ?: null,
        ]);

        $this->audit('oidc_authorize', ['ip' => $request->ip(), 'detail' => "client=$clientId"]);

        return $flow;
    }

    /** Finn en ventende, ikke-utløpt flyt på flow_id (brukes av innloggingssidene). */
    public function pendingFlow(?string $flowId): ?OidcAuthFlow
    {
        if (!$flowId) {
            return null;
        }
        $flow = OidcAuthFlow::where('flow_id', $flowId)->first();
        if (!$flow || $flow->status !== 'pending') {
            return null;
        }
        if ($flow->expires_at && $flow->expires_at->isPast()) {
            $flow->update(['status' => 'expired']);
            return null;
        }
        return $flow;
    }

    /**
     * Fullfør flyten for en innlogget bruker og returner URL-en klienten skal sendes til (code + state).
     * $source: 'entra' | 'signicat' | 'qr_preauth' | 'session' (allerede innlogget i Laravel).
     */
    public function complete(OidcAuthFlow $flow, User $user, string $source, string $acr, array $amr): string
    {
        $flow->update([
            'status' => 'completed',
            'source' => $source,
            'user_id' => $user->id,
            'email' => $user->email ?? '',
            'name' => $user->full_name ?? '',
            'nin_hash' => $user->nin_hash ?? '',
            'acr' => $acr,
            'amr' => $amr,
            'completed_at' => now(),
            // Koden er gyldig i 10 min fra fullføring, ikke fra start
            'expires_at' => now()->addSeconds(self::AUTH_CODE_TTL),
        ]);
        $this->audit('oidc_authorize', [
            'actor_user_id' => $user->id, 'actor_email' => $user->email, 'nin_hash' => $user->nin_hash,
            'detail' => "completed via $source", 'ip' => $flow->ip,
        ]);
        return $this->redirectWithParams($flow->redirect_uri, ['code' => $flow->auth_code, 'state' => $flow->state]);
    }

    /** Marker flyten som feilet og lag redirect til klienten med error (OAuth2-standard). */
    public function fail(OidcAuthFlow $flow, string $error, string $description = ''): string
    {
        $flow->update(['status' => 'failed', 'error' => $error]);
        $params = ['error' => $error, 'state' => $flow->state];
        if ($description !== '') {
            $params['error_description'] = $description;
        }
        return $this->redirectWithParams($flow->redirect_uri, $params);
    }

    private function redirectWithParams(string $uri, array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        return $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query($params);
    }

    // ---------- token ----------

    /** POST /oidc/token – returnerer token-svaret som array. Kaster OidcException. */
    public function token(Request $request): array
    {
        $p = $this->tokenParams($request);
        [$clientId, $clientSecret] = $this->clientCredentials($request, $p);

        return match ($p['grant_type'] ?? '') {
            'authorization_code' => $this->grantAuthorizationCode($p, $clientId, $clientSecret),
            'refresh_token' => $this->grantRefreshToken($p, $clientId, $clientSecret),
            default => throw new OidcException('unsupported_grant_type', 'grant_type må være authorization_code eller refresh_token'),
        };
    }

    /** Base44 sendte både form-encoded og JSON – støtt begge. */
    private function tokenParams(Request $request): array
    {
        if ($request->isJson()) {
            return (array) $request->json()->all();
        }
        return $request->post();
    }

    /** client_secret_basic (Authorization-header), client_secret_post (body) eller none. */
    private function clientCredentials(Request $request, array $p): array
    {
        $id = (string) ($p['client_id'] ?? '');
        $secret = (string) ($p['client_secret'] ?? '');
        $auth = (string) $request->header('Authorization', '');
        if (Str::startsWith($auth, 'Basic ')) {
            $raw = base64_decode(substr($auth, 6), true);
            if ($raw !== false && str_contains($raw, ':')) {
                [$bid, $bsecret] = explode(':', $raw, 2);
                $id = rawurldecode($bid);
                $secret = rawurldecode($bsecret);
            }
        }
        return [$id, $secret];
    }

    /** Sjekk at klienten finnes og at eventuell hemmelighet stemmer. Klient uten secret = public client (PKCE). */
    private function authenticateClient(string $clientId, string $clientSecret): array
    {
        $client = $clientId !== '' ? $this->client($clientId) : null;
        if (!$client) {
            throw new OidcException('invalid_client', 'Ukjent klient', 401);
        }
        $expected = (string) ($client['secret'] ?? '');
        if ($expected !== '' && !hash_equals($expected, $clientSecret)) {
            throw new OidcException('invalid_client', 'Feil client_secret', 401);
        }
        return $client;
    }

    private function grantAuthorizationCode(array $p, string $clientId, string $clientSecret): array
    {
        $code = (string) ($p['code'] ?? '');
        if ($code === '') {
            throw new OidcException('invalid_grant', 'code mangler');
        }
        $flow = OidcAuthFlow::where('auth_code', $code)->first();
        if (!$flow || $flow->status !== 'completed' || !$flow->user_id) {
            throw new OidcException('invalid_grant', 'Ukjent eller ufullstendig kode');
        }
        // Klient: Base44 sendte client_id bare i Basic-headeren; mangler den helt, bruk flytens.
        if ($clientId === '') {
            $clientId = (string) $flow->client_id;
        }
        $this->authenticateClient($clientId, $clientSecret);
        if ($flow->client_id !== $clientId) {
            throw new OidcException('invalid_grant', 'Koden tilhører en annen klient');
        }
        if ($flow->used_at) {
            // Gjenbruk av kode: trekk tilbake alt utstedt for flyten (RFC 6749 §4.1.2)
            $flow->update(['access_token' => null, 'refresh_token' => null, 'status' => 'failed', 'error' => 'code_reuse']);
            throw new OidcException('invalid_grant', 'Koden er allerede brukt');
        }
        if ($flow->expires_at && $flow->expires_at->isPast()) {
            $flow->update(['status' => 'expired']);
            throw new OidcException('invalid_grant', 'Koden er utløpt');
        }
        $redirectUri = (string) ($p['redirect_uri'] ?? '');
        if ($redirectUri !== '' && $redirectUri !== $flow->redirect_uri) {
            throw new OidcException('invalid_grant', 'redirect_uri stemmer ikke');
        }
        // PKCE
        if ($flow->code_challenge) {
            $verifier = (string) ($p['code_verifier'] ?? '');
            if ($verifier === '') {
                throw new OidcException('invalid_grant', 'code_verifier mangler');
            }
            $computed = Jwt::b64url(hash('sha256', $verifier, true));
            if (!hash_equals($flow->code_challenge, $computed)) {
                throw new OidcException('invalid_grant', 'code_verifier stemmer ikke med code_challenge');
            }
        }

        $user = User::find($flow->user_id);
        if (!$user) {
            throw new OidcException('invalid_grant', 'Brukeren finnes ikke lenger');
        }

        $flow->forceFill(['used_at' => now()]);
        return $this->issueTokens($flow, $user, withRefresh: true, event: 'oidc_token_issued');
    }

    private function grantRefreshToken(array $p, string $clientId, string $clientSecret): array
    {
        $rt = (string) ($p['refresh_token'] ?? '');
        if ($rt === '') {
            throw new OidcException('invalid_grant', 'refresh_token mangler');
        }
        $flow = OidcAuthFlow::where('refresh_token', $rt)->first();
        if (!$flow || $flow->status !== 'completed' || !$flow->user_id) {
            throw new OidcException('invalid_grant', 'Ukjent refresh_token');
        }
        if ($clientId === '') {
            $clientId = (string) $flow->client_id;
        }
        $this->authenticateClient($clientId, $clientSecret);
        if ($flow->client_id !== $clientId) {
            throw new OidcException('invalid_grant', 'refresh_token tilhører en annen klient');
        }
        if ($flow->refresh_expires_at && $flow->refresh_expires_at->isPast()) {
            $flow->update(['refresh_token' => null, 'status' => 'expired']);
            throw new OidcException('invalid_grant', 'refresh_token er utløpt');
        }
        $user = User::find($flow->user_id);
        if (!$user) {
            throw new OidcException('invalid_grant', 'Brukeren finnes ikke lenger');
        }
        // Tilgang kan ha utløpt (access_until) eller rolle endret siden innlogging – sjekk mot opprinnelig innloggingsmåte
        $provider = $flow->source === 'entra' ? User::PROVIDER_ENTRA : ($flow->source === 'signicat' ? User::PROVIDER_BANKID : (string) ($user->identity_provider ?: $flow->source));
        if (!$user->canLogin($provider)) {
            $flow->update(['refresh_token' => null, 'access_token' => null, 'status' => 'failed', 'error' => 'access_revoked']);
            throw new OidcException('invalid_grant', $user->loginDeniedReason($provider) ?? 'Tilgang avvist');
        }
        // Rotasjon: nytt refresh_token hver gang
        return $this->issueTokens($flow, $user, withRefresh: true, event: 'oidc_token_refreshed');
    }

    /** Lag id_token/access_token(/refresh_token), lagre på flyten og returner token-svaret. */
    private function issueTokens(OidcAuthFlow $flow, User $user, bool $withRefresh, string $event): array
    {
        $key = $this->keys->signingKey();
        $now = time();
        $claims = array_filter([
            'iss' => $this->issuer(),
            'sub' => $user->id,
            'aud' => $flow->client_id,
            'exp' => $now + self::ACCESS_TOKEN_TTL,
            'iat' => $now,
            'auth_time' => $flow->completed_at?->getTimestamp(),
            'nonce' => $flow->nonce ?: null,
            'acr' => $flow->acr ?: null,
            'amr' => is_array($flow->amr) && $flow->amr ? $flow->amr : null,
        ], fn ($v) => $v !== null) + $this->userClaims($user);

        $idToken = Jwt::sign($claims, $key->private_pem, $key->kid);
        $accessToken = Jwt::rand(32);

        $patch = [
            'access_token' => $accessToken,
            'access_expires_at' => now()->addSeconds(self::ACCESS_TOKEN_TTL),
        ];
        if ($withRefresh) {
            $patch['refresh_token'] = Jwt::rand(32);
            $patch['refresh_expires_at'] = now()->addSeconds(self::REFRESH_TOKEN_TTL);
        }
        $flow->forceFill($patch)->save();

        $this->audit($event, ['actor_user_id' => $user->id, 'actor_email' => $user->email, 'nin_hash' => $user->nin_hash]);

        $out = [
            'token_type' => 'Bearer',
            'id_token' => $idToken,
            'access_token' => $accessToken,
            'expires_in' => self::ACCESS_TOKEN_TTL,
            'scope' => $flow->scope ?: 'openid profile email',
        ];
        if ($withRefresh) {
            $out['refresh_token'] = $patch['refresh_token'];
        }
        return $out;
    }

    /** Claims om brukeren (id_token + userinfo) – som oidcProvider.ts, pluss rolle/app/identitetsfelt. */
    public function userClaims(User $user): array
    {
        return [
            'email' => $user->email,
            'email_verified' => true,
            'name' => $user->full_name ?? '',
            'role' => $user->role,
            'app_id' => $user->app_id,
            'identity_provider' => $user->identity_provider,
            'bankid_verified' => (bool) $user->bankid_verified,
            'nin_level' => $user->nin_level,
            'nin_hash' => $user->nin_hash ?? '',
        ];
    }

    // ---------- userinfo ----------

    /** GET /oidc/userinfo – Bearer access_token. Kaster OidcException(invalid_token, 401). */
    public function userinfo(Request $request): array
    {
        $token = trim((string) preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', '')));
        if ($token === '') {
            throw new OidcException('invalid_token', 'Mangler Bearer-token', 401);
        }
        $flow = OidcAuthFlow::where('access_token', $token)->first();
        if (!$flow || !$flow->user_id) {
            throw new OidcException('invalid_token', 'Ukjent token', 401);
        }
        $expires = $flow->access_expires_at ?? $flow->used_at?->addSeconds(self::ACCESS_TOKEN_TTL);
        if ($expires && $expires->isPast()) {
            throw new OidcException('invalid_token', 'Token er utløpt', 401);
        }
        $user = User::find($flow->user_id);
        if (!$user) {
            throw new OidcException('invalid_token', 'Brukeren finnes ikke', 401);
        }
        $this->audit('oidc_userinfo', ['actor_user_id' => $user->id, 'actor_email' => $user->email]);
        return ['sub' => $user->id] + $this->userClaims($user);
    }

    // ---------- audit ----------

    /** AuditEvent som audit() i oidcProvider.ts – må aldri velte flyten. */
    public function audit(string $eventType, array $ev = []): void
    {
        try {
            AuditEvent::create([
                'event_type' => $eventType,
                'actor_user_id' => $ev['actor_user_id'] ?? '',
                'actor_email' => $ev['actor_email'] ?? '',
                'nin_hash' => $ev['nin_hash'] ?? '',
                'ip' => $ev['ip'] ?? (request()?->ip() ?? ''),
                'detail' => $ev['detail'] ?? '',
                'success' => $ev['success'] ?? true,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }
}
