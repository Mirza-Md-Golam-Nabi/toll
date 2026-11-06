<?php

namespace App\Services\Reports;

use App\Models\TollTransaction;
use App\Services\TransactionAggregates;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every report is one GROUP BY query over toll_transactions using the shared
 * aggregate columns — no per-row lookups, no N+1.
 */
class ReportService
{
    /**
     * Short-lived cache for dashboard widgets that poll (same filters => same answer).
     *
     * @param  'totals'|'byDay'|'byCategory'|'byShiftSession'|'byOperator'  $report
     */
    public function cached(string $report, ReportFilters $filters, int $seconds = 30): mixed
    {
        // Only plain arrays are cached (Laravel refuses to unserialize objects from the cache).
        $value = Cache::remember("toll.report.{$report}.".$filters->cacheKey(), $seconds, fn (): array => collect($this->{$report}($filters))->all());

        return $report === 'totals' ? $value : collect($value);
    }

    public function query(ReportFilters $filters): Builder
    {
        return $filters->apply(TollTransaction::query());
    }

    public function totals(ReportFilters $filters): array
    {
        $row = $this->query($filters)->selectRaw(TransactionAggregates::columns())->toBase()->first();

        return TransactionAggregates::normalize($row);
    }

    /** Daily collection — every day in the range is present (zero-filled) so charts have no gaps. */
    public function byDay(ReportFilters $filters): Collection
    {
        $rows = $this->grouped($filters, [DB::raw('DATE(toll_transactions.issued_at) AS day')], ['day'])
            ->get()
            ->keyBy('day');

        return collect(CarbonPeriod::create($filters->from->startOfDay(), '1 day', $filters->until->startOfDay()))
            ->map(fn ($date) => ['label' => $date->toDateString()] + TransactionAggregates::normalize($rows->get($date->toDateString())))
            ->values();
    }

    /** Category-wise collection. */
    public function byCategory(ReportFilters $filters): Collection
    {
        return $this->grouped($filters, [
            'vehicle_categories.id AS category_id',
            'vehicle_categories.name_bn',
            'vehicle_categories.name_en',
            'vehicle_categories.color',
        ], ['vehicle_categories.id', 'vehicle_categories.name_bn', 'vehicle_categories.name_en', 'vehicle_categories.color', 'vehicle_categories.sort_order'])
            ->join('vehicle_categories', 'vehicle_categories.id', '=', 'toll_transactions.vehicle_category_id')
            ->orderBy('vehicle_categories.sort_order')
            ->get()
            ->map(fn (object $row) => [
                'label' => app()->getLocale() === 'bn' ? $row->name_bn : $row->name_en,
                'color' => $row->color,
            ] + TransactionAggregates::normalize($row));
    }

    /** Shift-wise collection (one row per shift session, with cash reconciliation). */
    public function byShiftSession(ReportFilters $filters): Collection
    {
        $columns = [
            'shift_sessions.id', 'shift_sessions.opened_at', 'shift_sessions.closed_at',
            'shift_sessions.expected_cash', 'shift_sessions.declared_cash', 'shift_sessions.cash_difference',
            'shifts.name AS shift_name', 'users.name AS operator_name', 'booths.code AS booth_code',
        ];

        return $this->grouped($filters, $columns, array_map(fn ($c) => explode(' AS ', $c)[0], $columns))
            ->join('shift_sessions', 'shift_sessions.id', '=', 'toll_transactions.shift_session_id')
            ->join('shifts', 'shifts.id', '=', 'shift_sessions.shift_id')
            ->join('users', 'users.id', '=', 'shift_sessions.user_id')
            ->join('booths', 'booths.id', '=', 'shift_sessions.booth_id')
            ->orderBy('shift_sessions.opened_at')
            ->get()
            ->map(fn (object $row) => [
                'label' => $row->shift_name,
                'operator' => $row->operator_name,
                'booth' => $row->booth_code,
                'opened_at' => $row->opened_at,
                'closed_at' => $row->closed_at,
                'expected_cash' => $row->expected_cash,
                'declared_cash' => $row->declared_cash,
                'cash_difference' => $row->cash_difference,
            ] + TransactionAggregates::normalize($row));
    }

    /** Operator-wise collection. */
    public function byOperator(ReportFilters $filters): Collection
    {
        return $this->grouped($filters, ['users.name AS operator_name'], ['users.id', 'users.name'])
            ->join('users', 'users.id', '=', 'toll_transactions.user_id')
            ->orderBy('users.name')
            ->get()
            ->map(fn (object $row) => ['label' => $row->operator_name] + TransactionAggregates::normalize($row));
    }

    /** Streams every matching ticket as CSV (Excel-friendly, UTF-8 BOM) using a cursor. */
    public function exportCsv(ReportFilters $filters): StreamedResponse
    {
        $filename = sprintf('toll-report-%s-to-%s.csv', $filters->from->toDateString(), $filters->until->toDateString());

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ticket', 'Issued at', 'Booth', 'Category', 'Plate', 'Amount', 'Status', 'Operator', 'Shift']);

            $rows = $this->query($filters)
                ->toBase()
                ->join('vehicle_categories', 'vehicle_categories.id', '=', 'toll_transactions.vehicle_category_id')
                ->join('users', 'users.id', '=', 'toll_transactions.user_id')
                ->join('booths', 'booths.id', '=', 'toll_transactions.booth_id')
                ->join('shift_sessions', 'shift_sessions.id', '=', 'toll_transactions.shift_session_id')
                ->join('shifts', 'shifts.id', '=', 'shift_sessions.shift_id')
                ->orderBy('toll_transactions.issued_at')
                ->select([
                    'toll_transactions.ticket_no', 'toll_transactions.issued_at', 'booths.code AS booth',
                    'vehicle_categories.name_en AS category', 'toll_transactions.plate_number',
                    'toll_transactions.amount', 'toll_transactions.status', 'users.name AS operator', 'shifts.name AS shift',
                ])
                ->cursor();

            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function grouped(ReportFilters $filters, array $select, array $groupBy): \Illuminate\Database\Query\Builder
    {
        return $this->query($filters)
            ->toBase()
            ->select($select)
            ->selectRaw(TransactionAggregates::columns())
            ->groupBy($groupBy);
    }
}
