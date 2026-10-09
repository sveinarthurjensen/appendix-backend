<?php

namespace App\Http\Controllers\Api;

use App\Functions\Base44Function;
use App\Functions\FunctionException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * POST /api/functions/{name}
 *
 * Funksjoner som er skrevet om til Laravel ligger i app/Functions/<Navn>.php og finnes automatisk.
 * Alt annet svarer 501 – frontend-shimen sender da kallet videre til Base44 (VITE_LARAVEL_FUNCTIONS styrer).
 */
class FunctionController extends Controller
{
    public function invoke(Request $request, string $name): JsonResponse
    {
        $class = 'App\\Functions\\' . Str::studly($name);
        if (!class_exists($class) || !is_subclass_of($class, Base44Function::class)) {
            return response()->json(['error' => "Funksjonen $name er ikke flyttet til Laravel ennå"], 501);
        }

        try {
            $result = app($class)($request->user(), $request->json()->all());
            return response()->json($result);
        } catch (FunctionException $e) {
            return response()->json(['error' => $e->getMessage()] + $e->extra, $e->status);
        }
    }

    /** GET /api/functions/exportPropertyIcal?… – offentlig iCal (Airbnb/Booking); rått text/calendar-svar */
    public function exportPropertyIcal(Request $request)
    {
        return app(\App\Functions\ExportPropertyIcal::class)->response($request->query());
    }

    // ---------- Offentlige endepunkt (routes/api.php utenfor auth:sanctum, throttle:30,1) ----------

    /** POST|OPTIONS /api/functions/mottaHenvendelse – kontaktskjemaene på aprop.no / geilolodge.com (CORS) */
    public function mottaHenvendelse(Request $request)
    {
        return app(\App\Functions\MottaHenvendelse::class)->response($request);
    }

    /** POST /api/functions/receiveLocationsData – webhook fra andre apper, Bearer-token */
    public function receiveLocationsData(Request $request)
    {
        return app(\App\Functions\ReceiveLocationsData::class)->response($request);
    }

    /** GET|OPTIONS /api/functions/offentligeUtleieobjekter – ledige utleieobjekter for nettsiden */
    public function offentligeUtleieobjekter(Request $request)
    {
        return app(\App\Functions\OffentligeUtleieobjekter::class)->response($request);
    }

    /** GET /api/functions/adminStatus – statusoversikt for Arbeidsflaten (header x-arbeidsflate-nokkel) */
    public function adminStatus(Request $request)
    {
        return app(\App\Functions\AdminStatus::class)->response($request);
    }

    /** Offentlige invitasjonsfunksjoner (kun tillatt liste) – ingen innlogging, kjøres uten bruker */
    public function publicFunction(Request $request, string $name): JsonResponse
    {
        abort_unless(in_array($name, ['resolveInviteCode', 'validateInviteToken'], true), 404);
        if ($request->isMethod('OPTIONS')) {
            return response()->json(null, 204);
        }
        $class = 'App\\Functions\\' . Str::studly($name);
        try {
            return response()->json(app($class)(null, $request->json()->all()));
        } catch (FunctionException $e) {
            return response()->json(['error' => $e->getMessage()] + $e->extra, $e->status);
        }
    }

    /** GET|POST /api/functions/getFeatureFlags – UI-flagg, ingen innlogging */
    public function getFeatureFlags(Request $request)
    {
        return app(\App\Functions\GetFeatureFlags::class)->response($request);
    }

    /** GET /api/functions – hvilke funksjoner som finnes i Laravel (brukes av shimen/feilsøking) */
    public function index(): JsonResponse
    {
        $names = [];
        foreach (glob(app_path('Functions/*.php')) as $f) {
            $cls = 'App\\Functions\\' . basename($f, '.php');
            if (is_subclass_of($cls, Base44Function::class)) {
                $names[] = lcfirst(basename($f, '.php'));
            }
        }
        sort($names);
        return response()->json($names);
    }
}
