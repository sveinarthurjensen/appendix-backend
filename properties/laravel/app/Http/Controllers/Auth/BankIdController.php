<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * BankID via Signicat (konsulenter, pasienter/parter) – STUB. Porteres i fase 6 del 2 fra
 * base44/functions/oidcSignicatCallback + Signicat-delen av oidcAuthorize (HMAC-signert state,
 * code-bytte hos Signicat, fnr → nin_hash → match/provisjonering, invite_token-kobling).
 * Konfig: services.signicat.client_id/client_secret (+ discovery_url kommer).
 */
class BankIdController extends Controller
{
    /** GET /auth/bankid/redirect?flow=… → 501 inntil Signicat-delen er portert */
    public function redirect(Request $request)
    {
        return response()->view('oidc.error', [
            'title' => 'BankID kommer',
            'message' => 'Innlogging med BankID er ikke flyttet til ny backend ennå. Bruk Microsoft-innlogging, eller prøv igjen senere.',
            'detail' => 'not_implemented',
        ], 501);
    }
}
