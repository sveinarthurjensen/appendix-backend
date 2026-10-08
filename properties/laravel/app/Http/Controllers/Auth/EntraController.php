<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Oidc\Jwt;
use App\Services\Oidc\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Innlogging med Microsoft Entra ID (faste ansatte) – authorization code + PKCE mot
 * login.microsoftonline.com/{tenant}/oauth2/v2.0. Egen app-registrering for innlogging
 * (services.entra_login.*), IKKE den som brukes til e-post/Graph (services.graph.*).
 *
 * Flyt: /auth/entra/redirect?flow=<oidc flow_id> → Microsoft → /auth/entra/callback
 *  - id_token verifiseres mot Microsofts JWKS (cache 12 t), iss/aud/nonce/exp sjekkes
 *  - bruker matches på entra_oid, deretter e-post (preferred_username/email/upn)
 *  - ukjente brukere avvises med feilside (ingen auto-provisjonering – brukerposten styrer tilgang)
 *  - users.identity_provider=entra, entra_oid, last_login_* settes, Laravel-sesjon (web guard) logges inn
 *  - pågående OIDC-flyt fullføres: redirect til klientens redirect_uri med code+state
 */
class EntraController extends Controller
{
    private const SESSION_KEY = 'entra_login';
    private const JWKS_CACHE_KEY = 'entra_login_jwks';

    public function __construct(private readonly Provider $oidc)
    {
    }

    public function redirect(Request $request)
    {
        $cfg = $this->config();
        if (!$cfg['tenant'] || !$cfg['client_id'] || !$cfg['client_secret']) {
            return $this->errorPage('Microsoft-innlogging er ikke konfigurert', 'ENTRA_LOGIN_TENANT_ID / ENTRA_LOGIN_CLIENT_ID / ENTRA_LOGIN_CLIENT_SECRET mangler.', 500);
        }

        $flowId = (string) $request->query('flow', '');
        if ($flowId !== '' && !$this->oidc->pendingFlow($flowId)) {
            return $this->errorPage('Innloggingen er utløpt', 'Start innloggingen på nytt fra appen.');
        }

        $verifier = Jwt::rand(48);
        $state = Jwt::rand(16);
        $nonce = Jwt::rand(16);
        $request->session()->put(self::SESSION_KEY, [
            'flow' => $flowId,
            'verifier' => $verifier,
            'state' => $state,
            'nonce' => $nonce,
            'started' => time(),
        ]);

        $params = [
            'client_id' => $cfg['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $cfg['redirect_uri'],
            'response_mode' => 'query',
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Jwt::b64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ];
        return redirect()->away($this->authority($cfg) . '/oauth2/v2.0/authorize?' . http_build_query($params));
    }

    public function callback(Request $request)
    {
        $cfg = $this->config();
        $sess = (array) $request->session()->pull(self::SESSION_KEY, []);
        $flow = $this->oidc->pendingFlow($sess['flow'] ?? null);

        if ($request->query('error')) {
            $desc = (string) $request->query('error_description', $request->query('error'));
            $this->oidc->audit('entra_login', ['success' => false, 'detail' => $desc]);
            return $this->deny($flow, 'access_denied', 'Microsoft-innlogging avbrutt', $desc);
        }
        if (empty($sess['state']) || !hash_equals($sess['state'], (string) $request->query('state', ''))) {
            return $this->deny($flow, 'invalid_request', 'Innloggingen kunne ikke fullføres', 'Ugyldig eller utløpt state – start på nytt.');
        }
        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->deny($flow, 'invalid_request', 'Innloggingen kunne ikke fullføres', 'Mangler code fra Microsoft.');
        }

        // Bytt code mot tokens
        $res = Http::asForm()->timeout(15)->post($this->authority($cfg) . '/oauth2/v2.0/token', [
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'],
            'code_verifier' => $sess['verifier'] ?? '',
            'scope' => 'openid profile email',
        ]);
        if (!$res->ok() || !$res->json('id_token')) {
            Log::warning('entra login: token-bytte feilet', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            return $this->deny($flow, 'server_error', 'Microsoft-innlogging feilet', 'Kunne ikke hente token fra Microsoft (' . $res->status() . ').');
        }

        // Verifiser id_token
        try {
            $claims = $this->verifyIdToken((string) $res->json('id_token'), $cfg, (string) ($sess['nonce'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('entra login: id_token ugyldig: ' . $e->getMessage());
            return $this->deny($flow, 'server_error', 'Microsoft-innlogging feilet', 'id_token kunne ikke verifiseres: ' . $e->getMessage());
        }

        $oid = (string) ($claims['oid'] ?? '');
        $email = strtolower(trim((string) ($claims['email'] ?? $claims['preferred_username'] ?? $claims['upn'] ?? '')));
        $name = (string) ($claims['name'] ?? '');

        // Match bruker: entra_oid først (stabil), deretter e-post
        $user = $oid !== '' ? User::where('entra_oid', $oid)->first() : null;
        if (!$user && $email !== '' && str_contains($email, '@')) {
            $user = User::whereRaw('lower(email) = ?', [$email])->first();
        }
        if (!$user) {
            $this->oidc->audit('entra_login', ['success' => false, 'actor_email' => $email, 'detail' => 'unknown_user oid=' . $oid]);
            return $this->deny($flow, 'access_denied', 'Ingen tilgang',
                "Microsoft-kontoen {$email} er ikke registrert som bruker. Be administrator opprette brukeren med denne e-postadressen.");
        }
        if ($reason = $user->loginDeniedReason(User::PROVIDER_ENTRA)) {
            $this->oidc->audit('entra_login', ['success' => false, 'actor_user_id' => $user->id, 'actor_email' => $user->email, 'detail' => $reason]);
            return $this->deny($flow, 'access_denied', 'Ingen tilgang', $reason);
        }

        // Oppdater brukerposten og logg inn i Laravel-sesjonen (web guard) – WebAuthn/QR bygger på denne senere
        $patch = [];
        if ($oid !== '' && $user->entra_oid !== $oid) {
            $patch['entra_oid'] = $oid;
        }
        if (!$user->full_name && $name !== '') {
            $patch['full_name'] = $name;
        }
        if ($patch) {
            $user->forceFill($patch)->save();
        }
        $user->markLoggedIn(User::PROVIDER_ENTRA);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->oidc->audit('entra_login', ['actor_user_id' => $user->id, 'actor_email' => $user->email, 'detail' => 'oid=' . $oid]);

        if ($flow) {
            return redirect()->away($this->oidc->complete($flow, $user, 'entra', 'urn:aprop:entra', ['entra', 'mfa']));
        }
        if (!empty($sess['flow'])) {
            // Flyten fra appen er utløpt/brukt mens brukeren var hos Microsoft
            Log::warning('Entra-callback: OIDC-flyt utløpt eller ikke ventende', ['flow' => $sess['flow'], 'user' => $user->email]);
            return $this->errorPage(
                'Innloggingsforespørselen er utløpt',
                'Du er logget inn med Microsoft, men forespørselen fra appen var utløpt (gyldig i 10 minutter). Gå tilbake til appen og trykk «Logg inn» på nytt.',
                400
            );
        }
        // Ingen OIDC-flyt (direkte innlogging) – vis kvittering
        return response()->view('oidc.error', [
            'title' => 'Innlogget',
            'message' => 'Du er logget inn som ' . $user->email . '. Du kan lukke dette vinduet og gå tilbake til appen.',
            'detail' => null,
            'back' => null,
        ]);
    }

    // ---------- hjelpere ----------

    private function config(): array
    {
        $c = (array) config('services.entra_login', []);
        return [
            'tenant' => (string) ($c['tenant'] ?? ''),
            'client_id' => (string) ($c['client_id'] ?? ''),
            'client_secret' => (string) ($c['client_secret'] ?? ''),
            'redirect_uri' => (string) ($c['redirect_uri'] ?: route('auth.entra.callback')),
        ];
    }

    private function authority(array $cfg): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($cfg['tenant']);
    }

    /** Verifiser signatur (JWKS, cache 12 t), iss, aud, nonce, exp. Returnerer claims. */
    private function verifyIdToken(string $idToken, array $cfg, string $nonce): array
    {
        $header = Jwt::decodeUnverified($idToken)['header'];
        $kid = (string) ($header['kid'] ?? '');
        $jwk = $this->findJwk($cfg, $kid);
        if (!$jwk) {
            // Nøkkelen kan være rotert etter at cachen ble lagd – hent på nytt én gang
            Cache::forget(self::JWKS_CACHE_KEY . ':' . $cfg['tenant']);
            $jwk = $this->findJwk($cfg, $kid);
        }
        if (!$jwk) {
            throw new \RuntimeException("Fant ikke signeringsnøkkel kid=$kid hos Microsoft");
        }
        $claims = Jwt::verify($idToken, Jwt::jwkToPem($jwk));

        $expectedIss = 'https://login.microsoftonline.com/' . $cfg['tenant'] . '/v2.0';
        $iss = (string) ($claims['iss'] ?? '');
        // Tenant-id i config kan være GUID eller verifisert domene; iss har alltid GUID
        if ($iss !== $expectedIss && (string) ($claims['tid'] ?? '') !== $cfg['tenant']) {
            throw new \RuntimeException("Feil utsteder: $iss");
        }
        if ((string) ($claims['aud'] ?? '') !== $cfg['client_id']) {
            throw new \RuntimeException('Feil aud i id_token');
        }
        if ($nonce !== '' && !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new \RuntimeException('Feil nonce i id_token');
        }
        return $claims;
    }

    private function findJwk(array $cfg, string $kid): ?array
    {
        $keys = Cache::remember(self::JWKS_CACHE_KEY . ':' . $cfg['tenant'], now()->addHours(12), function () use ($cfg) {
            $res = Http::timeout(10)->get($this->authority($cfg) . '/discovery/v2.0/keys');
            if (!$res->ok()) {
                throw new \RuntimeException('Kunne ikke hente Microsofts JWKS (' . $res->status() . ')');
            }
            return (array) $res->json('keys', []);
        });
        foreach ($keys as $k) {
            if (($k['kid'] ?? null) === $kid) {
                return $k;
            }
        }
        return null;
    }

    /** Avvis: har vi en OIDC-flyt, vises feilsiden med lenke tilbake til klienten (ikke automatisk redirect, så brukeren ser hvorfor). */
    private function deny($flow, string $error, string $title, string $detail)
    {
        $back = null;
        if ($flow) {
            $back = $this->oidc->fail($flow, $error, $detail);
        }
        return $this->errorPage($title, $detail, 403, $back);
    }

    private function errorPage(string $title, string $detail, int $status = 400, ?string $back = null)
    {
        return response()->view('oidc.error', [
            'title' => $title,
            'message' => $detail,
            'detail' => null,
            'back' => $back,
        ], $status);
    }
}
