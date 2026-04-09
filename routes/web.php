<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'check']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('woopack.auth');
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    Route::middleware('woopack.auth')->group(function (): void {
        Route::get('/integration', [IntegrationController::class, 'show']);
        Route::put('/integration', [IntegrationController::class, 'update']);
        Route::post('/integration/test', [IntegrationController::class, 'test']);
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('woopack.admin');
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::post('/orders/{id}/pack', [OrderController::class, 'pack'])->whereNumber('id');
        Route::get('/stats', [OrderController::class, 'stats']);
    });
});

Route::view('/{any?}', 'app')
    ->where('any', '.*')
    ->name('login');
