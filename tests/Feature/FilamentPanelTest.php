<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Settings;
use App\Filament\Pages\TollCollection;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Booths\BoothResource;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\ShiftSessions\ShiftSessionResource;
use App\Filament\Resources\TollTransactions\TollTransactionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\VehicleCategories\VehicleCategoryResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\TollTransactionService;
use Livewire\Livewire;

beforeEach(function () {
    $this->setup = tollSetup();
    openShift($this->setup);

    // Several rows so lazy-loading violations (N+1) would throw.
    VehicleCategory::factory()->withRate(20)->count(2)->create();
    foreach (range(1, 3) as $i) {
        app(TollTransactionService::class)->issue($this->setup->operator, $this->setup->car, "DHAKA-KA 11-000{$i}");
    }
});

it('renders every resource and page for a super admin without N+1 queries', function (string $url) {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get($url)
        ->assertOk();
})->with([
    'dashboard' => fn () => Dashboard::getUrl(),
    'reports' => fn () => Reports::getUrl(),
    'settings' => fn () => Settings::getUrl(),
    'tickets' => fn () => TollTransactionResource::getUrl(),
    'ticket view' => fn () => TollTransactionResource::getUrl('view', ['record' => TollTransaction::query()->first()]),
    'shift sessions' => fn () => ShiftSessionResource::getUrl(),
    'categories' => fn () => VehicleCategoryResource::getUrl(),
    'category edit' => fn () => VehicleCategoryResource::getUrl('edit', ['record' => VehicleCategory::query()->first()]),
    'vehicles' => fn () => VehicleResource::getUrl(),
    'shifts' => fn () => ShiftResource::getUrl(),
    'booths' => fn () => BoothResource::getUrl(),
    'users' => fn () => UserResource::getUrl(),
    'audit logs' => fn () => AuditLogResource::getUrl(),
]);

it('keeps operators out of management screens', function () {
    $this->actingAs($this->setup->operator);

    $this->get(UserResource::getUrl())->assertForbidden();
    $this->get(Reports::getUrl())->assertForbidden();
    $this->get(TollCollection::getUrl())->assertOk();
});

it('issues a ticket from the collection screen', function () {
    $this->actingAs($this->setup->operator);

    Livewire::test(TollCollection::class)
        ->set('plateNumber', 'narail-ga 12-3456')
        ->call('issue', $this->setup->car->id)
        ->assertHasNoErrors()
        ->assertDispatched('toll-print-receipt')
        ->assertSet('plateNumber', null);

    expect(TollTransaction::query()->where('plate_number', 'NARAIL-GA 12-3456')->exists())->toBeTrue();
});

it('shows the browser receipt to its operator', function () {
    $ticket = TollTransaction::query()->first();

    $this->actingAs($this->setup->operator)
        ->get(route('receipts.print', $ticket))
        ->assertOk()
        ->assertSee($ticket->ticket_no);
});
