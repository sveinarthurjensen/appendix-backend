<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\FunctionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// ---------- Offentlige funksjoner (ingen innlogging) ----------
// Må registreres FØR den generelle POST /functions/{name} i auth-gruppen, ellers fanger den opp kallene.
// Samme stier som nettsidene/andre apper bruker i dag (…/functions/<navn>), så bare vertsnavnet endres.
Route::middleware('throttle:30,1')->group(function () {
    // Kontaktskjema aprop.no / geilolodge.com (WordPress) – CORS håndteres i MottaHenvendelse::response
    Route::match(['POST', 'OPTIONS'], '/functions/mottaHenvendelse', [FunctionController::class, 'mottaHenvendelse']);
    // Webhook fra andre apper (Prime Leie, Medhjelp, Holding) – Authorization: Bearer <RECEIVE_LOCATIONS_TOKEN>
    Route::post('/functions/receiveLocationsData', [FunctionController::class, 'receiveLocationsData']);
    // Ledige utleieobjekter for nettsiden aprop.no (WordPress mellomlagrer 10 min)
    Route::match(['GET', 'OPTIONS'], '/functions/offentligeUtleieobjekter', [FunctionController::class, 'offentligeUtleieobjekter']);
    // Statusoversikt for Arbeidsflaten – header x-arbeidsflate-nokkel
    Route::get('/functions/adminStatus', [FunctionController::class, 'adminStatus']);
    // UI-feature-flagg
    Route::match(['GET', 'POST'], '/functions/getFeatureFlags', [FunctionController::class, 'getFeatureFlags']);
    // Offentlig iCal-eksport (Airbnb/Booking henter denne uten innlogging)
    Route::get('/functions/exportPropertyIcal', [FunctionController::class, 'exportPropertyIcal']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/entities/{entity}', [EntityController::class, 'index']);
    Route::post('/entities/{entity}', [EntityController::class, 'store']);
    Route::get('/entities/{entity}/{id}', [EntityController::class, 'show']);
    Route::patch('/entities/{entity}/{id}', [EntityController::class, 'update']);
    Route::put('/entities/{entity}/{id}', [EntityController::class, 'update']);
    Route::delete('/entities/{entity}/{id}', [EntityController::class, 'destroy']);

    Route::get('/functions', [FunctionController::class, 'index']);
    Route::post('/functions/{name}', [FunctionController::class, 'invoke']);
});

Route::get('/_diag', \App\Http\Controllers\Api\DiagController::class)->middleware('throttle:20,1');

Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);
