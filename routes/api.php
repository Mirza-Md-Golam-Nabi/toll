<?php

use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\AuthenticateBooth;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')
    ->middleware(['throttle:120,1', AuthenticateBooth::class])
    ->name('sync.')
    ->group(function (): void {
        Route::post('push', [SyncController::class, 'push'])->name('push');
        Route::get('master', [SyncController::class, 'master'])->name('master');
    });
