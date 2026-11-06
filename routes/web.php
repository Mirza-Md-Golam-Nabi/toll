<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['bn', 'en'])
    ->name('locale.switch');

Route::middleware('auth')->group(function (): void {
    Route::get('/receipts/{transaction}', ReceiptController::class)->name('receipts.print');
});
