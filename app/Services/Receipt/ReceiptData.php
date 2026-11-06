<?php

namespace App\Services\Receipt;

use App\Enums\TransactionStatus;
use App\Models\TollTransaction;
use App\Support\Settings;

/**
 * Printer-agnostic receipt content. Both the ESC/POS printer and the browser
 * receipt view render from this, so the receipt layout is defined only once.
 */
final readonly class ReceiptData
{
    /**
     * @param  array<int, array{0: string, 1: string}>  $rows  label => value pairs
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public string $header,
        public array $rows,
        public string $amount,
        public string $footer,
        public ?string $helpline,
        public string $qrPayload,
        public bool $duplicate,
        public bool $exempt,
    ) {}

    public static function fromTransaction(TollTransaction $transaction): self
    {
        $transaction->loadMissing(['category:id,code,name_en,name_bn', 'operator:id,name', 'booth:id,code,name']);

        $rows = array_filter([
            ['Ticket', $transaction->ticket_no],
            ['Date', $transaction->issued_at->format('d/m/Y h:i:s A')],
            ['Booth', $transaction->booth->code.' - '.$transaction->booth->name],
            ['Vehicle', $transaction->category->name_en],
            $transaction->plate_number ? ['Plate', $transaction->plate_number] : null,
            ['Operator', $transaction->operator->name],
            $transaction->status === TransactionStatus::Exempt ? ['Exempt', (string) $transaction->exempt_reason] : null,
        ]);

        return new self(
            title: Settings::get('bridge_name_en'),
            subtitle: Settings::get('bridge_name_bn'),
            header: Settings::get('receipt_header'),
            rows: array_values($rows),
            amount: 'Tk '.number_format((float) $transaction->amount, 2),
            footer: Settings::get('receipt_footer'),
            helpline: Settings::get('helpline') ?: null,
            qrPayload: $transaction->ticket_no,
            duplicate: $transaction->print_count > 1,
            exempt: $transaction->status === TransactionStatus::Exempt,
        );
    }
}
