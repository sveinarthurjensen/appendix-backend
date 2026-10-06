<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/functions/{name}
 *
 * Spor A: Deno-funksjonene fra Base44 kjører videre i en egen container
 * (tjenesten `functions` i docker-compose) og kalles herfra med service-token.
 * Spor B: funksjoner som er skrevet om til Laravel registreres i $native og
 * kjøres direkte – én og én, etter hvert som de rører dem.
 */
class FunctionController extends Controller
{
    /** navn → invokable klasse */
    private array $native = [
        // 'sendSms' => \App\Functions\SendSms::class,
    ];

    public function invoke(Request $request, Http $http, string $name): JsonResponse
    {
        if (isset($this->native[$name])) {
            return response()->json(app($this->native[$name])($request->user(), $request->json()->all()));
        }

        $base = config('services.functions.url');
        abort_if(!$base, 501, "Funksjonen $name er ikke tilgjengelig ennå");

        $resp = $http->withToken(config('services.functions.token'))
            ->withHeaders([
                'X-User-Id' => $request->user()?->id,
                'X-User-Email' => $request->user()?->email,
                'X-User-Role' => $request->user()?->role,
            ])
            ->timeout(60)
            ->post(rtrim($base, '/') . '/' . $name, $request->json()->all());

        return response()->json($resp->json(), $resp->status());
    }
}
