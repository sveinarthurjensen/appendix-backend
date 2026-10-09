<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Step-up: krever at innloggingen har et visst nivå og er fersk nok. Brukes som
 *   ->middleware(RequireAuthLevel::class . ':bankid,10')   // BankID innen 10 min
 *   ->middleware(RequireAuthLevel::class . ':strong,30')   // BankID ELLER Entra med MFA innen 30 min
 * Nivåer ut fra amr i innloggingen (OidcAuthFlow): bankid → 'bankid'; strong → 'bankid' eller 'mfa'; otp er aldri sterk.
 * Alder regnes fra completed_at (selve innloggingen), ikke fra token-fornyelse. Sanctum/passord-økter er aldri step-up.
 * Svar ved mangel: 403 {error: step_up_required, required, max_age_minutes} – klienten starter da ny innlogging
 * (OIDC authorize med prompt=login og ønsket metode) og prøver på nytt.
 */
class RequireAuthLevel
{
    public static function satisfied(?\App\Models\OidcAuthFlow $flow, string $level, int $minutes): bool
    {
        if (!$flow || !$flow->completed_at) {
            return false;
        }
        $amr = is_array($flow->amr) ? $flow->amr : [];
        $ok = match ($level) {
            'bankid' => in_array('bankid', $amr, true),
            'strong' => in_array('bankid', $amr, true) || in_array('mfa', $amr, true),
            default => false,
        };
        return $ok && $flow->completed_at->gte(now()->subMinutes($minutes));
    }

    public function handle(Request $request, Closure $next, string $level = 'strong', int|string $minutes = 30)
    {
        if (!self::satisfied($request->attributes->get('oidc_flow'), $level, (int) $minutes)) {
            return response()->json([
                'error' => 'step_up_required',
                'message' => 'Ekstra bekreftelse kreves for denne handlingen.',
                'required' => $level,
                'max_age_minutes' => (int) $minutes,
            ], 403);
        }
        return $next($request);
    }
}
