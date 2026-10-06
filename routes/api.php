<?php

use App\Http\Controllers\Api\AssetScanController;
use App\Http\Controllers\Api\TokenController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * The asset scanner app. It sends a Bearer token, never a cookie, so the app can live
 * on another domain without CSRF or cross-site cookie trouble.
 */
Route::post('/tokens', [TokenController::class, 'login'])->middleware('throttle:10,1');
Route::post('/tokens/demo/{account}', [TokenController::class, 'demo'])
    ->whereIn('account', User::DEMO_LOGINS)->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/tokens/current', [TokenController::class, 'logout']);
    Route::get('/assets/{tag}', [AssetScanController::class, 'show']);
    Route::post('/assets/{tag}/hand-over', [AssetScanController::class, 'handOver']);
    Route::post('/assets/{tag}/receive-return', [AssetScanController::class, 'receiveReturn']);
});
