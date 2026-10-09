<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Oidc\Provider;
use App\Services\OtpLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Engangskode-innlogging (SMS/e-post) – begrenset nivå (acr urn:appendix:acr:otp, amr ['otp']).
 *  POST /api/auth/otp/request {channel: sms|email, target, client_id}
 *  POST /api/auth/otp/verify  {channel, target, code, client_id} → {access_token, refresh_token, id_token, …}
 * Offentlig, men strengt rate-limitert. Aldri for ansatte/admin; ingen BankID-status gis.
 */
class OtpController extends Controller
{
    private function clientAllowed(string $clientId): bool
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) env('OTP_CLIENT_IDS', ''))));
        return $clientId !== '' && in_array($clientId, $allowed, true);
    }

    public function request(Request $request, OtpLogin $otp): JsonResponse
    {
        $d = $request->validate(['channel' => 'required|in:sms,email', 'target' => 'required|string|max:190', 'client_id' => 'required|string|max:100']);
        abort_unless($this->clientAllowed($d['client_id']), 403, 'Klienten har ikke tilgang til kode-innlogging');
        $r = $otp->request($d['channel'], $d['target'], $request->ip());
        return response()->json(['message' => $r['message']] + (isset($r['expires_in']) ? ['expires_in' => $r['expires_in']] : []), $r['status']);
    }

    public function verify(Request $request, OtpLogin $otp, Provider $oidc): JsonResponse
    {
        $d = $request->validate(['channel' => 'required|in:sms,email', 'target' => 'required|string|max:190', 'code' => 'required|string|max:12', 'client_id' => 'required|string|max:100']);
        abort_unless($this->clientAllowed($d['client_id']), 403, 'Klienten har ikke tilgang til kode-innlogging');
        $user = $otp->verify($d['channel'], $d['target'], $d['code'], $request->ip());
        if (!$user) {
            return response()->json(['message' => 'Ugyldig eller utløpt kode'], 401);
        }
        $user->markLoggedIn('otp');
        return response()->json($oidc->issueDirect($user, $d['client_id'], 'otp', 'urn:appendix:acr:otp', ['otp'], $request->ip()));
    }
}
