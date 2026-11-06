<?php

namespace App\Http\Controllers;

use App\Models\TollTransaction;
use App\Services\Receipt\ReceiptData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Browser-printable 58mm receipt (fallback when direct ESC/POS printing is off or fails).
 */
class ReceiptController extends Controller
{
    public function __invoke(TollTransaction $transaction): View
    {
        Gate::authorize('view', $transaction);

        return view('receipts.ticket', ['receipt' => ReceiptData::fromTransaction($transaction)]);
    }
}
