<?php

use App\Http\Controllers\Auth\BankIdController;
use App\Http\Controllers\Auth\EntraController;
use App\Http\Controllers\Oidc\ProviderController;
use Illuminate\Support\Facades\Route;

/*
 * Web-ruter (sesjon/cookies, web guard). Kopieres til src/routes/web.php av install-properties.sh.
 *
 * OIDC-utsteder (fase 6): Laravel er identity provider for klient-appene. CSRF-unntak for
 * token-endepunktet må legges i bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->validateCsrfTokens(except: ['oidc/token', 'functions/oidcToken']);
 *   })
 */

// Forside – enkel statusside i stedet for 404
Route::view('/', 'oidc.error', [
    'title' => 'Appendix API',
    'message' => 'Felles backend og innlogging for Appendix-konsernet.',
    'detail' => null,
    'back' => null,
])->name('home');

// Discovery
Route::get('/.well-known/openid-configuration', [ProviderController::class, 'discovery'])->name('oidc.discovery');

// OIDC-endepunkt (kanoniske stier)
Route::prefix('oidc')->name('oidc.')->group(function () {
    Route::get('/authorize', [ProviderController::class, 'authorizeRequest'])->name('authorize');
    Route::post('/token', [ProviderController::class, 'token'])->middleware('throttle:60,1')->name('token');
    Route::get('/userinfo', [ProviderController::class, 'userinfo'])->name('userinfo');
    Route::get('/jwks', [ProviderController::class, 'jwks'])->name('jwks');
});

// Alias: samme URL-form som Base44-utstederen (${issuer}/functions/oidcAuthorize osv.) så
// eksisterende klient-apper virker uten endring.
Route::get('/functions/oidcDiscovery', [ProviderController::class, 'discovery']);
Route::get('/functions/oidcAuthorize', [ProviderController::class, 'authorizeRequest']);
Route::post('/functions/oidcToken', [ProviderController::class, 'token'])->middleware('throttle:60,1');
Route::get('/functions/oidcUserinfo', [ProviderController::class, 'userinfo']);
Route::get('/functions/oidcJwks', [ProviderController::class, 'jwks']);

// Innlogging (tre veier inn): Entra (faste ansatte), BankID (konsulenter/parter – stub), WebAuthn/QR (kommer)
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/entra/redirect', [EntraController::class, 'redirect'])->name('entra.redirect');
    Route::get('/entra/callback', [EntraController::class, 'callback'])->name('entra.callback');
    Route::get('/bankid/redirect', [BankIdController::class, 'redirect'])->name('bankid.redirect'); // 501 «kommer»
});
