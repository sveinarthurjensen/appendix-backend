<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Oidc\OidcException;
use App\Services\Oidc\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * HTTP-endepunktene for OIDC-utstederen (routes/web.php). Erstatter Base44-funksjonene
 * oidcDiscovery, oidcAuthorize, oidcToken, oidcUserinfo og oidcJwks; alias-rutene /functions/oidc*
 * peker hit så eksisterende klient-apper virker uendret.
 */
class ProviderController extends Controller
{
    public function __construct(private readonly Provider $oidc)
    {
    }

    /** GET /.well-known/openid-configuration (+ /functions/oidcDiscovery) */
    public function discovery()
    {
        return response()->json($this->oidc->discovery())
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** GET /oidc/jwks (+ /functions/oidcJwks) */
    public function jwks()
    {
        return response()->json($this->oidc->jwks())
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * GET /oidc/authorize (+ /functions/oidcAuthorize)
     * Lagrer flyten og viser innloggingsvalg. Er brukeren allerede innlogget i Laravel-sesjonen
     * (web guard) og fortsatt har lov, fullføres flyten direkte (SSO) – med mindre prompt=login.
     */
    public function authorizeRequest(Request $request)
    {
        try {
            $flow = $this->oidc->startAuthorize($request);
        } catch (OidcException $e) {
            // Ugyldig klient/redirect_uri skal ALDRI redirectes – vis feilside
            return response()->view('oidc.error', [
                'title' => 'Ugyldig innloggingsforespørsel',
                'message' => $e->getMessage(),
                'detail' => $e->error,
            ], $e->status);
        }

        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        if ($user && $request->query('prompt') !== 'login' && !$flow->invite_token) {
            $provider = (string) ($user->last_login_provider ?: $user->identity_provider ?: '');
            if ($provider !== '' && $user->canLogin($provider)) {
                $amr = $provider === User::PROVIDER_ENTRA ? ['entra', 'mfa'] : [$provider];
                return redirect()->away($this->oidc->complete($flow, $user, 'session', 'urn:aprop:session', $amr));
            }
        }

        return response()->view('oidc.login', [
            'flow' => $flow,
            'clientName' => $this->oidc->client((string) $flow->client_id)['name'] ?? $flow->client_id,
            'entraUrl' => route('auth.entra.redirect', ['flow' => $flow->flow_id]),
            'bankidUrl' => route('auth.bankid.redirect', ['flow' => $flow->flow_id]),
        ]);
    }

    /** POST /oidc/token (+ /functions/oidcToken) – CSRF-unntak, se bootstrap/app.php */
    public function token(Request $request)
    {
        try {
            return response()->json($this->oidc->token($request))
                ->header('Cache-Control', 'no-store')
                ->header('Pragma', 'no-cache');
        } catch (OidcException $e) {
            return response()->json($e->toArray(), $e->status)->header('Cache-Control', 'no-store');
        }
    }

    /** GET /oidc/userinfo (+ /functions/oidcUserinfo) */
    public function userinfo(Request $request)
    {
        try {
            return response()->json($this->oidc->userinfo($request));
        } catch (OidcException $e) {
            return response()->json($e->toArray(), $e->status)
                ->header('WWW-Authenticate', 'Bearer error="' . $e->error . '"');
        }
    }
}
