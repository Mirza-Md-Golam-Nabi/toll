<?php

namespace App\Support;

/**
 * One place for currency formatting so tables, widgets, reports and receipts agree.
 */
class Money
{
    public static function format(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $withSymbol ? Settings::get('currency', '৳').' '.$formatted : $formatted;
    }
}
