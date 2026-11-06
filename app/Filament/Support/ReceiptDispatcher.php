<?php

namespace App\Filament\Support;

use App\Models\TollTransaction;
use App\Services\Receipt\ReceiptPrinter;
use Livewire\Component;

/**
 * Prints a receipt directly on the thermal printer, or tells the browser to print it
 * through a hidden iframe (see resources/js/toll.js).
 */
final class ReceiptDispatcher
{
    public static function send(Component $livewire, TollTransaction $transaction): void
    {
        if (app(ReceiptPrinter::class)->print($transaction)) {
            return;
        }

        $livewire->dispatch('toll-print-receipt', url: route('receipts.print', $transaction));
    }
}
