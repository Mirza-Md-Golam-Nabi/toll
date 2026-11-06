<?php

use App\Models\TollTransaction;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;

beforeEach(function () {
    $this->setup = tollSetup();
    $this->session = openShift($this->setup);
    $this->reports = app(ReportService::class);

    $ticket = fn () => TollTransaction::factory()->for($this->session)->state([
        'booth_id' => $this->setup->booth->id,
        'user_id' => $this->setup->operator->id,
        'vehicle_category_id' => $this->setup->car->id,
        'amount' => 50,
    ]);

    $ticket()->count(3)->create();
    $ticket()->exempt()->create();
    $ticket()->voided()->create();
    $ticket()->create(['issued_at' => now()->subDays(2)]);
});

it('totals only counted tickets', function () {
    expect($this->reports->totals(ReportFilters::today()))->toBe([
        'vehicles' => 4,
        'revenue' => 150.0,
        'paid_count' => 3,
        'exempt_count' => 1,
        'voided_count' => 1,
    ]);
});

it('groups by category and zero-fills missing days', function () {
    $days = $this->reports->byDay(ReportFilters::lastDays(3));

    expect($days)->toHaveCount(3)
        ->and($days->pluck('vehicles')->all())->toBe([1, 0, 4])
        ->and($this->reports->byCategory(ReportFilters::today())->first()['revenue'])->toBe(150.0);
});

it('streams a CSV export', function () {
    $response = $this->reports->exportCsv(ReportFilters::today());

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect(substr_count($csv, "\n"))->toBe(6);
});
