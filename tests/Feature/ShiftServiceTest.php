<?php

use App\Enums\ShiftStatus;
use App\Exceptions\TollException;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use App\Services\TollTransactionService;

beforeEach(function () {
    $this->setup = tollSetup();
});

it('allows only one open shift per booth', function () {
    openShift($this->setup);
    $other = User::factory()->operator($this->setup->booth)->create();

    app(ShiftService::class)->open($other, $this->setup->shift);
})->throws(TollException::class);

it('reconciles cash when a shift is closed', function () {
    $session = openShift($this->setup, 500);
    $service = app(TollTransactionService::class);
    $service->issue($this->setup->operator, $this->setup->car);
    $service->issue($this->setup->operator, $this->setup->car);

    $closed = app(ShiftService::class)->close($session, 590, 'Short by 10');

    expect($closed->status)->toBe(ShiftStatus::Closed)
        ->and($closed->expected_cash)->toBe('600.00')
        ->and($closed->cash_difference)->toBe('-10.00')
        ->and($closed->transaction_count)->toBe(2);
});

it('detects the shift that covers an overnight time', function () {
    $night = Shift::factory()->create(['name' => 'Night', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'sort_order' => 0]);

    expect($night->covers(now()->setTime(23, 30)))->toBeTrue()
        ->and($night->covers(now()->setTime(3, 0)))->toBeTrue()
        ->and($night->covers(now()->setTime(12, 0)))->toBeFalse();
});
