<?php

namespace App\Services\Receipt;

use App\Models\TollTransaction;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

/**
 * Prints a receipt straight to a 58mm ESC/POS thermal printer.
 * Returns false when direct printing is disabled or fails, so the UI can fall back
 * to the browser receipt page.
 */
class ReceiptPrinter
{
    public function isDirect(): bool
    {
        return config('toll.printer.driver') === 'escpos';
    }

    public function print(TollTransaction $transaction): bool
    {
        if (! $this->isDirect()) {
            return false;
        }

        $printer = null;

        try {
            $printer = new Printer($this->connector());
            $this->render($printer, ReceiptData::fromTransaction($transaction));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            $printer?->close();
        }
    }

    private function render(Printer $printer, ReceiptData $receipt): void
    {
        $width = (int) config('toll.printer.chars_per_line');

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text($receipt->title."\n");
        $printer->selectPrintMode();
        $printer->text($receipt->header."\n");

        if ($receipt->duplicate) {
            $printer->setReverseColors(true);
            $printer->text(" DUPLICATE \n");
            $printer->setReverseColors(false);
        }

        $printer->text(str_repeat('-', $width)."\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        foreach ($receipt->rows as [$label, $value]) {
            $printer->text($this->line($label, $value, $width));
        }

        $printer->text(str_repeat('-', $width)."\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
        $printer->text(($receipt->exempt ? 'EXEMPT ' : '').$receipt->amount."\n");
        $printer->selectPrintMode();
        $printer->qrCode($receipt->qrPayload, Printer::QR_ECLEVEL_M, 5);
        $printer->text($receipt->footer."\n");

        if ($receipt->helpline) {
            $printer->text('Helpline: '.$receipt->helpline."\n");
        }

        $printer->feed(2);
        $printer->cut();
    }

    /** "Label: ........ value" padded to the paper width. */
    private function line(string $label, string $value, int $width): string
    {
        $label .= ':';
        $space = max(1, $width - mb_strlen($label) - mb_strlen($value));

        return $label.str_repeat(' ', $space).$value."\n";
    }

    private function connector(): PrintConnector
    {
        $target = (string) config('toll.printer.target');

        return match (config('toll.printer.connector')) {
            'network' => new NetworkPrintConnector($target, (int) config('toll.printer.port')),
            'file' => new FilePrintConnector($target),
            default => new WindowsPrintConnector($target),
        };
    }
}
