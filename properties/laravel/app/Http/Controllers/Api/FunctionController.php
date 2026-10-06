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
