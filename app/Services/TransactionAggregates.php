<?php

namespace App\Services;

use App\Enums\TransactionStatus;

/**
 * The one definition of "vehicles / revenue / exempt / voided" aggregate columns,
 * shared by shift closing, dashboard widgets and every report.
 */
class TransactionAggregates
{
    public static function columns(string $table = 'toll_transactions'): string
    {
        $paid = TransactionStatus::Paid->value;
        $exempt = TransactionStatus::Exempt->value;
        $voided = TransactionStatus::Voided->value;

        return implode(', ', [
            "COUNT(CASE WHEN {$table}.status IN ('{$paid}', '{$exempt}') THEN 1 END) AS vehicles",
            "COALESCE(SUM(CASE WHEN {$table}.status = '{$paid}' THEN {$table}.amount END), 0) AS revenue",
            "COUNT(CASE WHEN {$table}.status = '{$paid}' THEN 1 END) AS paid_count",
            "COUNT(CASE WHEN {$table}.status = '{$exempt}' THEN 1 END) AS exempt_count",
            "COUNT(CASE WHEN {$table}.status = '{$voided}' THEN 1 END) AS voided_count",
        ]);
    }

    /** Normalises a raw aggregate row to typed values. */
    public static function normalize(?object $row): array
    {
        return [
            'vehicles' => (int) ($row->vehicles ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
            'paid_count' => (int) ($row->paid_count ?? 0),
            'exempt_count' => (int) ($row->exempt_count ?? 0),
            'voided_count' => (int) ($row->voided_count ?? 0),
        ];
    }
}
