<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\FunctionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/entities/{entity}', [EntityController::class, 'index']);
    Route::post('/entities/{entity}', [EntityController::class, 'store']);
    Route::get('/entities/{entity}/{id}', [EntityController::class, 'show']);
    Route::patch('/entities/{entity}/{id}', [EntityController::class, 'update']);
    Route::put('/entities/{entity}/{id}', [EntityController::class, 'update']);
    Route::delete('/entities/{entity}/{id}', [EntityController::class, 'destroy']);

    Route::post('/functions/{name}', [FunctionController::class, 'invoke']);
});

Route::get('/health', fn () => ['ok' => true, 'time' => now()->toIso8601String()]);
