<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReceiptController;
use App\Support\MobileApp;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['bn', 'en'])
    ->name('locale.switch');

// Offline Android collection app (PWA). Its service worker and assets live in public/mobile.
Route::get('/mobile/app', fn () => view('mobile.app', ['version' => MobileApp::VERSION]))->name('mobile.app');

Route::middleware('auth')->group(function (): void {
    Route::get('/receipts/{transaction}', ReceiptController::class)->name('receipts.print');
});
