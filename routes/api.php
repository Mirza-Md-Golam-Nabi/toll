<?php

use App\Http\Controllers\Api\MobileController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\AuthenticateBooth;
use App\Http\Middleware\AuthenticateMobileDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')
    ->middleware(['throttle:120,1', AuthenticateBooth::class])
    ->name('sync.')
    ->group(function (): void {
        Route::post('push', [SyncController::class, 'push'])->name('push');
        Route::get('master', [SyncController::class, 'master'])->name('master');
    });

Route::prefix('mobile')
    ->name('mobile.')
    ->group(function (): void {
        Route::get('ping', [MobileController::class, 'ping'])->middleware('throttle:120,1')->name('ping');
        Route::post('login', [MobileController::class, 'login'])->middleware('throttle:10,1')->name('login');

        Route::middleware(['throttle:120,1', AuthenticateMobileDevice::class])->group(function (): void {
            Route::get('bootstrap', [MobileController::class, 'bootstrap'])->name('bootstrap');
            Route::post('push', [MobileController::class, 'push'])->name('push');
            Route::post('logout', [MobileController::class, 'logout'])->name('logout');
        });
    });
