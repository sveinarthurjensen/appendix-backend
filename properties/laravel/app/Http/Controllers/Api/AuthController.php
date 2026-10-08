<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Midlertidig innlogging for pilotfasen (e-post + passord → Sanctum-token).
 * Erstattes av OIDC/BankID (fase 6) – frontend-shimen bruker bare token-headeren,
 * så byttet skjer uten endringer i sidene.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required']);
        $user = \App\Models\User::where('email', $data['email'])->first();
        if (!$user || !$user->password || !\Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Feil e-post eller passord'], 401);
        }
        $token = $user->createToken('web', ['*'], now()->addDays(14))->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user->toBase44Array()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->toBase44Array());
    }

    public function logout(Request $request): JsonResponse
    {
        $flow = $request->attributes->get('oidc_flow');
        if ($flow) {
            $flow->forceFill(['access_token' => null, 'refresh_token' => null])->save();
        } else {
            $request->user()->currentAccessToken()?->delete();
        }
        return response()->json(['success' => true]);
    }
}
