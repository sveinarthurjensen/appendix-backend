<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\IdentityMatching;
use App\Services\InviteEvents;
use App\Services\Oidc\Jwt;
use App\Services\Oidc\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Innlogging med BankID via Signicat (konsulenter, pasienter/parter) – authorization code + PKCE.
 * Portert fra base44/functions/oidcSignicatCallback + Signicat-delen av oidcAuthorize.
 *
 * Flyt: /auth/bankid/redirect?flow=<oidc flow_id> → Signicat (BankID) → /auth/bankid/callback
 *  - id_token verifiseres mot Signicats JWKS (discovery, cache 12 t), iss/aud/nonce/exp sjekkes
 *  - fødselsnummer hentes fra claims (id_token, deretter userinfo), hashes (SHA-256) og LAGRES ALDRI i klartekst
 *  - invitasjon (flow.invite_token): fnr bindes til forhåndsopprettet konto; avvises om invitasjonen
 *    hadde forhåndsregistrert fnr som ikke matcher
 *  - ellers match på nin_hash / national_id_hash
 *
 * AVVIK fra Base44 (beslutning 8.10.2026, tilgangsstyring før helsedata):
 *  - ingen auto-provisjonering av ukjente personer (bare invitasjon eller forhåndsregistrert fnr)
 *  - ingen e-post-matching (BankID gir ikke verifisert e-post)
 *  - BankID gir aldri admin (ADMIN_NIN_HASHES droppet; admin krever Entra – se User::loginDeniedReason)
 */
class BankIdController extends Controller
{
    private const SESSION_KEY = 'bankid_login';
    private const ACR = 'urn:signicat:method:bankid';

    public function __construct(private readonly Provider $oidc)
    {
    }

    /** GET /auth/bankid/redirect?flow=… */
    public function redirect(Request $request)
    {
        $cfg = $this->config();
        if (!$cfg['client_id'] || !$cfg['client_secret'] || !$cfg['discovery_url']) {
            return $this->errorPage('BankID er ikke konfigurert', 'BankID-tilkoblingen er ikke satt opp ennå (SIGNICAT_CLIENT_ID / SIGNICAT_CLIENT_SECRET / SIGNICAT_DISCOVERY_URL). Bruk Microsoft-innlogging, eller kontakt administrator.', 503);
        }

        $flowId = (string) $request->query('flow', '');
        if ($flowId !== '' && !$this->oidc->pendingFlow($flowId)) {
            return $this->errorPage('Innloggingen er utløpt', 'Gå tilbake til appen og trykk «Logg inn» på nytt.');
        }

        try {
            $disc = $this->discovery($cfg);
        } catch (\Throwable $e) {
            Log::warning('bankid: discovery feilet: ' . $e->getMessage());
            return $this->errorPage('BankID er utilgjengelig', 'Kunne ikke kontakte Signicat. Prøv igjen om litt.', 502);
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

        $params = array_filter([
            'client_id' => $cfg['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $cfg['redirect_uri'],
            'scope' => $cfg['scope'],
            'state' => $state,
            'nonce' => $nonce,
            'acr_values' => $cfg['acr_values'],
            'code_challenge' => Jwt::b64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt' => 'login',
            'ui_locales' => 'nb',
        ], fn ($v) => $v !== '' && $v !== null);

        $ep = (string) $disc['authorization_endpoint'];
        return redirect()->away($ep . (str_contains($ep, '?') ? '&' : '?') . http_build_query($params));
    }

    /** GET /auth/bankid/callback?code=…&state=… */
    public function callback(Request $request)
    {
        $cfg = $this->config();
        $sess = (array) $request->session()->pull(self::SESSION_KEY, []);
        $flow = $this->oidc->pendingFlow($sess['flow'] ?? null);

        if ($err = $request->query('error')) {
            $desc = (string) $request->query('error_description', $err);
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'detail' => "error=$err $desc"]);
            return $this->deny($flow, 'access_denied', $err === 'access_denied' ? 'BankID avbrutt' : 'BankID-innlogging feilet', $desc);
        }
        if (empty($sess['state']) || !hash_equals($sess['state'], (string) $request->query('state', ''))) {
            return $this->deny($flow, 'invalid_request', 'Innloggingen kunne ikke fullføres', 'Ugyldig eller utløpt forespørsel – start innloggingen på nytt fra appen.');
        }
        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->deny($flow, 'invalid_request', 'Innloggingen kunne ikke fullføres', 'Manglende svar fra BankID – prøv igjen.');
        }

        try {
            $disc = $this->discovery($cfg);
        } catch (\Throwable $e) {
            return $this->deny($flow, 'server_error', 'BankID er utilgjengelig', 'Kunne ikke kontakte Signicat. Prøv igjen om litt.');
        }

        // Bytt code mot tokens (client_secret_basic, med client_secret_post som fallback)
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'],
            'code_verifier' => $sess['verifier'] ?? '',
        ];
        $res = Http::asForm()->timeout(15)->withBasicAuth($cfg['client_id'], $cfg['client_secret'])
            ->post($disc['token_endpoint'], $form);
        if (in_array($res->status(), [400, 401], true) && str_contains((string) $res->body(), 'invalid_client')) {
            $res = Http::asForm()->timeout(15)->post($disc['token_endpoint'], $form + [
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
            ]);
        }
        if (!$res->ok() || !$res->json('id_token')) {
            Log::warning('bankid: token-bytte feilet', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'detail' => 'token_exchange_' . $res->status()]);
            return $this->deny($flow, 'server_error', 'BankID-bekreftelse feilet', 'Kunne ikke verifisere BankID (' . $res->status() . '). Prøv igjen.');
        }

        try {
            $claims = $this->verifyIdToken((string) $res->json('id_token'), $cfg, $disc, (string) ($sess['nonce'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('bankid: id_token ugyldig: ' . $e->getMessage());
            return $this->deny($flow, 'server_error', 'BankID-bekreftelse feilet', 'Svaret fra BankID kunne ikke verifiseres.');
        }

        $userinfo = [];
        if (!empty($disc['userinfo_endpoint']) && $res->json('access_token')) {
            try {
                $ui = Http::timeout(10)->withToken((string) $res->json('access_token'))->get($disc['userinfo_endpoint']);
                if ($ui->ok()) {
                    $userinfo = (array) $ui->json();
                }
            } catch (\Throwable) {
            }
        }

        $fnr = IdentityMatching::extractNationalId($claims) ?? IdentityMatching::extractNationalId($userinfo);
        if (!$fnr) {
            Log::warning('bankid: ingen fnr i claims', ['claim_keys' => array_keys($claims + $userinfo)]);
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'detail' => 'no_fnr']);
            return $this->deny($flow, 'access_denied', 'Identitet mangler', 'BankID returnerte ikke fødselsnummer. Kontroller at BankID er godkjent og prøv igjen.');
        }
        $ninHash = IdentityMatching::hashNin($fnr);
        $last4 = IdentityMatching::nationalIdLast4($fnr);
        unset($fnr); // fnr i klartekst lagres aldri
        $name = trim((string) ($claims['name'] ?? $userinfo['name'] ?? trim(($claims['given_name'] ?? '') . ' ' . ($claims['family_name'] ?? ''))));

        // ---- Invitasjon: bind BankID-identiteten til forhåndsopprettet konto ----
        $source = 'signicat';
        $user = null;
        if ($flow && $flow->invite_token) {
            $result = $this->acceptInvite($flow, $ninHash, $name);
            if ($result instanceof User) {
                $user = $result;
                $source = 'invite';
            } elseif ($result !== null) {
                return $result; // feilside
            }
        }

        // ---- Ellers: forhåndsregistrert fnr ----
        $user ??= IdentityMatching::matchUserByNinHash($ninHash);
        if (!$user) {
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'nin_hash' => $ninHash, 'detail' => 'no_registered_user']);
            return $this->deny($flow, 'access_denied', 'Bruker ikke registrert',
                'Det finnes ingen bruker knyttet til din BankID. Kontakt den som skal gi deg tilgang for å få en invitasjon.');
        }
        if ($reason = $user->loginDeniedReason(User::PROVIDER_BANKID)) {
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'actor_user_id' => $user->id, 'actor_email' => $user->email, 'detail' => $reason]);
            return $this->deny($flow, 'access_denied', 'Ingen tilgang', $reason);
        }

        $now = now();
        $patch = [
            'nin_hash' => $ninHash,
            'nin_verified_at' => $now,
            'nin_level' => max(2, (int) $user->nin_level),
            'bankid_verified' => true,
            'bankid_verified_at' => $now,
        ];
        if (!$user->national_id_hash) {
            $patch += ['national_id_hash' => $ninHash, 'national_id_last4' => $last4, 'national_id_verified_at' => $now, 'national_id_source' => 'bankid'];
        }
        if (!$user->full_name && $name !== '') {
            $patch['full_name'] = $name;
        }
        $user->forceFill($patch)->save();
        $user->markLoggedIn(User::PROVIDER_BANKID);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->oidc->audit('oidc_signicat_login', ['actor_user_id' => $user->id, 'actor_email' => $user->email, 'nin_hash' => $ninHash, 'detail' => $source === 'invite' ? 'invite_linked' : 'existing_user']);

        if ($flow) {
            return redirect()->away($this->oidc->complete($flow, $user, $source, self::ACR, ['bankid']));
        }
        if (!empty($sess['flow'])) {
            return $this->errorPage('Innloggingsforespørselen er utløpt',
                'Du er bekreftet med BankID, men forespørselen fra appen var utløpt (gyldig i 10 minutter). Gå tilbake til appen og trykk «Logg inn» på nytt.');
        }
        return response()->view('oidc.error', [
            'title' => 'Innlogget',
            'message' => 'Du er logget inn med BankID. Du kan lukke dette vinduet og gå tilbake til appen.',
            'detail' => null,
            'back' => null,
        ]);
    }

    /**
     * Returnerer User ved vellykket kobling, en Response (feilside) ved avvisning,
     * eller null hvis invitasjonen ikke er gyldig (faller da tilbake til vanlig matching).
     */
    private function acceptInvite($flow, string $ninHash, string $name)
    {
        $inv = UserInvite::where('token', $flow->invite_token)->first();
        $valid = $inv && $inv->status === 'sent' && !$inv->used_at && $inv->expires_at && $inv->expires_at->isFuture();
        if (!$valid) {
            return null;
        }

        if ($inv->nin_hash && !hash_equals((string) $inv->nin_hash, $ninHash)) {
            InviteEvents::log(['invite_id' => $inv->id, 'event_type' => 'akseptert', 'actor_email' => (string) $inv->email, 'invite_email' => (string) $inv->email,
                'detail' => 'BankID-fnr matcher ikke forhåndsregistrert fnr – avvist']);
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'actor_email' => $inv->email, 'nin_hash' => $ninHash, 'detail' => 'invite_nin_mismatch']);
            return $this->deny($flow, 'access_denied', 'BankID matcher ikke invitert person',
                'Fødselsnummeret bekreftet med BankID stemmer ikke med personen som ble invitert. Kontakt administrator for en ny invitasjon.');
        }

        $user = $inv->target_user_id ? User::find($inv->target_user_id) : null;
        if (!$user && $inv->email) {
            $user = User::whereRaw('lower(email) = ?', [strtolower((string) $inv->email)])->first();
        }
        if (!$user) {
            InviteEvents::log(['invite_id' => $inv->id, 'event_type' => 'akseptert', 'invite_email' => (string) $inv->email,
                'detail' => 'BankID bekreftet, men ingen forhåndsopprettet konto å koble til']);
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'actor_email' => $inv->email, 'nin_hash' => $ninHash, 'detail' => 'invite_no_preallocated_user']);
            return $this->deny($flow, 'access_denied', 'Brukerkonto mangler',
                'BankID er bekreftet, men invitasjonen er ikke koblet til en brukerkonto. Be administrator sende en ny invitasjon.');
        }

        // En annen konto har allerede denne BankID-identiteten → ikke bind samme person til to kontoer
        $other = IdentityMatching::matchUserByNinHash($ninHash);
        if ($other && $other->id !== $user->id) {
            $this->oidc->audit('oidc_signicat_login', ['success' => false, 'actor_email' => $inv->email, 'nin_hash' => $ninHash, 'detail' => 'invite_nin_belongs_to_other_user']);
            return $this->deny($flow, 'access_denied', 'BankID er allerede i bruk',
                'Denne BankID-identiteten er allerede knyttet til en annen brukerkonto. Kontakt administrator.');
        }

        $patch = [];
        // BankID kan aldri gi admin – en admin-invitasjon må fullføres med Microsoft-innlogging
        if ($inv->role && $inv->role !== 'admin' && $user->role !== $inv->role) {
            $patch['role'] = $inv->role;
        }
        if (!$user->full_name && ($inv->name || $name)) {
            $patch['full_name'] = $inv->name ?: $name;
        }
        if ($patch) {
            $user->forceFill($patch)->save();
        }
        $inv->update(['status' => 'used', 'used_at' => now(), 'used_flow_id' => $flow->id, 'target_user_id' => $user->id, 'nin_hash' => $ninHash]);
        InviteEvents::log(['invite_id' => $inv->id, 'event_type' => 'akseptert', 'actor_name' => $user->email ?: 'Mottaker',
            'actor_email' => (string) $user->email, 'invite_email' => (string) ($inv->email ?: $user->email), 'detail' => 'BankID bekreftet – invitasjon akseptert']);
        return $user;
    }

    // ---------- hjelpere ----------

    private function config(): array
    {
        $c = (array) config('services.signicat', []);
        return [
            'client_id' => (string) ($c['client_id'] ?? ''),
            'client_secret' => (string) ($c['client_secret'] ?? ''),
            'discovery_url' => self::normalizeDiscovery((string) ($c['discovery_url'] ?? '')),
            'scope' => (string) (($c['scope'] ?? '') ?: 'openid profile nin'),
            'acr_values' => (string) (($c['acr_values'] ?? '') ?: 'idp:nbid'),
            'redirect_uri' => (string) (($c['redirect_uri'] ?? '') ?: route('auth.bankid.callback')),
        ];
    }

    /** Godta både issuer-URL og (avkuttet) .well-known-URL – bygg alltid full discovery-adresse. */
    private static function normalizeDiscovery(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $pos = stripos($url, '/.well-known');
        $base = rtrim($pos === false ? $url : substr($url, 0, $pos), '/');
        return $base . '/.well-known/openid-configuration';
    }

    private function discovery(array $cfg): array
    {
        return Cache::remember('signicat_discovery:' . md5($cfg['discovery_url']), now()->addHours(12), function () use ($cfg) {
            $res = Http::timeout(10)->get($cfg['discovery_url']);
            if (!$res->ok() || !$res->json('authorization_endpoint')) {
                throw new \RuntimeException('Signicat discovery feilet (' . $res->status() . ')');
            }
            return (array) $res->json();
        });
    }

    private function verifyIdToken(string $idToken, array $cfg, array $disc, string $nonce): array
    {
        $kid = (string) (Jwt::decodeUnverified($idToken)['header']['kid'] ?? '');
        $jwk = $this->findJwk($disc, $kid, false) ?? $this->findJwk($disc, $kid, true);
        if (!$jwk) {
            throw new \RuntimeException("Fant ikke signeringsnøkkel kid=$kid hos Signicat");
        }
        $claims = Jwt::verify($idToken, Jwt::jwkToPem($jwk));
        if (($claims['iss'] ?? '') !== ($disc['issuer'] ?? '')) {
            throw new \RuntimeException('Feil utsteder: ' . ($claims['iss'] ?? ''));
        }
        $aud = (array) ($claims['aud'] ?? []);
        if (!in_array($cfg['client_id'], $aud, true)) {
            throw new \RuntimeException('Feil aud i id_token');
        }
        if ($nonce !== '' && !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new \RuntimeException('Feil nonce i id_token');
        }
        return $claims;
    }

    private function findJwk(array $disc, string $kid, bool $refresh): ?array
    {
        $key = 'signicat_jwks:' . md5((string) ($disc['jwks_uri'] ?? ''));
        if ($refresh) {
            Cache::forget($key);
        }
        $keys = Cache::remember($key, now()->addHours(12), function () use ($disc) {
            $res = Http::timeout(10)->get((string) $disc['jwks_uri']);
            if (!$res->ok()) {
                throw new \RuntimeException('Kunne ikke hente Signicats JWKS (' . $res->status() . ')');
            }
            return (array) $res->json('keys', []);
        });
        foreach ($keys as $k) {
            if (($k['kid'] ?? null) === $kid && ($k['use'] ?? 'sig') === 'sig') {
                return $k;
            }
        }
        return null;
    }

    private function deny($flow, string $error, string $title, string $detail)
    {
        $back = $flow ? $this->oidc->fail($flow, $error, $detail) : null;
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
