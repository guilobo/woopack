<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'check']);

    Route::middleware('woopack.auth')->group(function (): void {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::post('/orders/{id}/pack', [OrderController::class, 'pack'])->whereNumber('id');
        Route::get('/stats', [OrderController::class, 'stats']);
    });
});

Route::view('/{any?}', 'app')
    ->where('any', '.*')
    ->name('login');
