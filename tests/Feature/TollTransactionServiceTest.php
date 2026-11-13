<?php

use App\Enums\AuditEvent;
use App\Enums\TransactionStatus;
use App\Exceptions\TollException;
use App\Models\AuditLog;
use App\Models\TollRate;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\ApprovalService;
use App\Services\RateService;
use App\Services\ShiftService;
use App\Services\TollTransactionService;

beforeEach(function () {
    $this->setup = tollSetup();
    $this->service = app(TollTransactionService::class);
});

it('refuses to issue a ticket without an open shift', function () {
    $this->service->issue($this->setup->operator, $this->setup->car);
})->throws(TollException::class);

it('issues a paid ticket at the current rate with a sequential ticket number', function () {
    openShift($this->setup);

    $first = $this->service->issue($this->setup->operator, $this->setup->car, 'dhaka metro-ga 12-3456');
    $second = $this->service->issue($this->setup->operator, $this->setup->car);

    expect($first->amount)->toBe('50.00')
        ->and($first->status)->toBe(TransactionStatus::Paid)
        ->and($first->plate_number)->toBe('DHAKA METRO-GA 12-3456')
        ->and($first->ticket_no)->toBe(config('toll.booth_code').'-'.now()->format('ymd').'-000001')
        ->and($second->ticket_no)->toEndWith('-000002')
        ->and(Vehicle::query()->where('plate_number', 'DHAKA METRO-GA 12-3456')->value('pass_count'))->toBe(1)
        ->and(AuditLog::query()->where('event', AuditEvent::TicketIssued)->count())->toBe(2);
});

it('counts repeat passes of the same plate', function () {
    openShift($this->setup);

    $this->service->issue($this->setup->operator, $this->setup->car, 'KHULNA-KA 11-1111');
    $this->service->issue($this->setup->operator, $this->setup->car, 'khulna-ka 11-1111');

    expect(Vehicle::query()->where('plate_number', 'KHULNA-KA 11-1111')->value('pass_count'))->toBe(2);
});

it('issues exempt tickets free of charge with an optional reason', function () {
    openShift($this->setup);
    $ambulance = VehicleCategory::factory()->exempt()->create();

    $withoutReason = $this->service->issue($this->setup->operator, $ambulance, exemptReason: '   ');
    $withReason = $this->service->issue($this->setup->operator, $ambulance, exemptReason: 'Emergency patient');

    expect($withoutReason->status)->toBe(TransactionStatus::Exempt)
        ->and($withoutReason->amount)->toBe('0.00')
        ->and($withoutReason->exempt_reason)->toBeNull()
        ->and($withReason->status)->toBe(TransactionStatus::Exempt)
        ->and($withReason->exempt_reason)->toBe('Emergency patient');
});

it('applies a new rate only from its effective time', function () {
    openShift($this->setup);
    TollRate::query()->create(['vehicle_category_id' => $this->setup->car->id, 'amount' => 70, 'effective_from' => now()->addHour()]);
    RateService::flush();

    expect($this->service->issue($this->setup->operator, $this->setup->car)->amount)->toBe('50.00');

    $this->travel(2)->hours();
    RateService::flush();

    expect($this->service->issue($this->setup->operator, $this->setup->car)->amount)->toBe('70.00');
});

it('lets an operator void a ticket only with a valid manager PIN', function () {
    openShift($this->setup);
    $ticket = $this->service->issue($this->setup->operator, $this->setup->car);
    $approvals = app(ApprovalService::class);

    expect(fn () => $approvals->resolve($this->setup->operator, $this->setup->manager->id, '0000'))
        ->toThrow(TollException::class);

    $approver = $approvals->resolve($this->setup->operator, $this->setup->manager->id, '1234');
    $this->service->void($ticket, 'Wrong lane', $this->setup->operator, $approver);

    $summary = app(ShiftService::class)->summary($ticket->shiftSession);

    expect($ticket->fresh()->status)->toBe(TransactionStatus::Voided)
        ->and($ticket->fresh()->approved_by)->toBe($this->setup->manager->id)
        ->and($summary['revenue'])->toBe(0.0)
        ->and($summary['voided_count'])->toBe(1)
        ->and(AuditLog::query()->where('event', AuditEvent::TicketVoided)->value('reason'))->toBe('Wrong lane');
});

it('does not ask managers for a PIN', function () {
    expect(app(ApprovalService::class)->resolve($this->setup->manager)->is($this->setup->manager))->toBeTrue();
});

it('re-prices a ticket when its category is corrected', function () {
    openShift($this->setup);
    $bus = VehicleCategory::factory()->withRate(150)->create();
    $ticket = $this->service->issue($this->setup->operator, $this->setup->car);

    $this->service->correctCategory($ticket, $bus, 'It was a bus', $this->setup->manager, $this->setup->manager);

    expect($ticket->fresh()->amount)->toBe('150.00')
        ->and($ticket->fresh()->vehicle_category_id)->toBe($bus->id);
});

it('marks reprints and refuses to touch voided tickets', function () {
    openShift($this->setup);
    $ticket = $this->service->issue($this->setup->operator, $this->setup->car);

    $this->service->registerReprint($ticket, $this->setup->operator);
    expect($ticket->fresh()->print_count)->toBe(2);

    $this->service->void($ticket, 'Test', $this->setup->manager, $this->setup->manager);
    $this->service->registerReprint($ticket->fresh(), $this->setup->operator);
})->throws(TollException::class);
