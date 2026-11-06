<?php

use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use App\Filament\Pages\TollCollection;
use App\Filament\Resources\TollTransactions\Pages\ListTollTransactions;
use App\Filament\Widgets\CategoryChart;
use App\Filament\Widgets\LatestTickets;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TollStatsOverview;
use App\Models\ShiftSession;
use App\Services\TollTransactionService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->setup = tollSetup();
});

it('renders every dashboard widget for managers', function (string $widget) {
    openShift($this->setup);
    app(TollTransactionService::class)->issue($this->setup->operator, $this->setup->car);
    app(TollTransactionService::class)->issue($this->setup->operator, $this->setup->car);

    $this->actingAs($this->setup->manager);

    Livewire::test($widget)->assertOk();
})->with([TollStatsOverview::class, RevenueChart::class, CategoryChart::class, LatestTickets::class]);

it('opens and closes a shift from the collection screen', function () {
    $this->actingAs($this->setup->operator);

    Livewire::test(TollCollection::class)
        ->callAction('openShift', data: ['shift_id' => $this->setup->shift->id, 'opening_cash' => 200])
        ->assertHasNoFormErrors()
        ->call('issue', $this->setup->car->id)
        ->callAction('closeShift', data: ['declared_cash' => 250])
        ->assertHasNoFormErrors();

    expect(ShiftSession::query()->sole())
        ->status->toBe(ShiftStatus::Closed)
        ->cash_difference->toBe('0.00');
});

it('voids a ticket from the table only with the manager PIN', function () {
    openShift($this->setup);
    $ticket = app(TollTransactionService::class)->issue($this->setup->operator, $this->setup->car);

    $this->actingAs($this->setup->operator);

    // A wrong PIN halts the action: the modal stays open and nothing changes.
    Livewire::test(ListTollTransactions::class)
        ->callAction(TestAction::make('void')->table($ticket), data: [
            'reason' => 'Duplicate print',
            'approver_id' => $this->setup->manager->id,
            'approval_pin' => '9999',
        ])
        ->assertActionMounted(TestAction::make('void')->table($ticket));
    expect($ticket->fresh()->status)->toBe(TransactionStatus::Paid);

    Livewire::test(ListTollTransactions::class)->callAction(TestAction::make('void')->table($ticket), data: [
        'reason' => 'Duplicate print',
        'approver_id' => $this->setup->manager->id,
        'approval_pin' => '1234',
    ]);
    expect($ticket->fresh()->status)->toBe(TransactionStatus::Voided);
});
