<?php

namespace App\Functions;

use App\Models\User;
use App\Services\PortalThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portert fra base44/functions/getFeatureFlags/entry.ts
 *
 * Feature-flagg for frontend (UI-relevante flagg). QR-hurtigstien er ALLTID på server-side,
 * så den er ikke et reelt flagg her. Krever ikke innlogging – svarer med faste verdier og
 * OIDC-issuer (config('services.portal.issuer') via PortalThread::issuer()).
 *
 * GET/POST /api/functions/getFeatureFlags → {passwordless_bridge, qr_fast_path, signicat_bankid, auto_provision_users, issuer}
 */
class GetFeatureFlags extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        return [
            'passwordless_bridge' => true,
            'qr_fast_path' => true,
            'signicat_bankid' => true,
            'auto_provision_users' => true,
            'issuer' => PortalThread::issuer(),
        ];
    }

    /** JSON-svar for den offentlige ruten. */
    public function response(Request $request): JsonResponse
    {
        try {
            return response()->json($this->__invoke(null, []));
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
