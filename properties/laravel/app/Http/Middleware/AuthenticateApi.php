<?php

namespace App\Http\Middleware;

use App\Models\OidcAuthFlow;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API-innlogging for frontend (SPA): godtar enten et OIDC access_token utstedt av oss
 * (Entra/BankID → /oidc/token) eller et Sanctum-token. OIDC-token slås opp i OidcAuthFlow,
 * sjekkes mot utløp og brukerens access_until, og brukeren settes som innlogget for requesten.
 */
class AuthenticateApi
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if ($token) {
            $flow = OidcAuthFlow::where('access_token', $token)->first();
            if ($flow && $flow->user_id) {
                $expires = $flow->access_expires_at;
                $user = User::find($flow->user_id);
                $expired = !$expires || $expires->isPast();
                $denied = !$user || ($user->access_until && $user->access_until->endOfDay()->isPast());
                if ($expired || $denied) {
                    return response()->json(['message' => 'Innloggingen er utløpt'], 401);
                }
                Auth::shouldUse('sanctum');
                Auth::guard('sanctum')->setUser($user);
                $request->setUserResolver(fn () => $user);
                $request->attributes->set('oidc_flow', $flow);
                return $next($request);
            }
        }
        return app(\Illuminate\Auth\Middleware\Authenticate::class)->handle($request, $next, 'sanctum');
    }
}
