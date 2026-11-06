<?php

use App\Models\ShiftSession;
use App\Models\TollTransaction;
use App\Models\Vehicle;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['toll.mode' => 'central', 'toll.sync.token' => 'secret-token']);
    $this->setup = tollSetup();
    $this->session = ShiftSession::factory()->make([
        'booth_id' => $this->setup->booth->id,
        'shift_id' => $this->setup->shift->id,
        'user_id' => $this->setup->operator->id,
    ]);
    $this->session->id = (string) Str::uuid();
});

function pushPayload(string $table, array $records): array
{
    return ['booth_code' => config('toll.booth_code'), 'table' => $table, 'records' => $records];
}

it('rejects requests without the sync token', function () {
    $this->postJson('/api/sync/push', pushPayload('shift_sessions', []))->assertUnauthorized();
});

it('stores pushed records idempotently and registers vehicles once', function () {
    $headers = ['Authorization' => 'Bearer secret-token'];
    $sessionRow = $this->session->getAttributes();

    $ticketRow = [
        'id' => (string) Str::uuid(),
        'ticket_no' => 'B01-261106-000001',
        'booth_id' => 999,
        'shift_session_id' => $this->session->id,
        'user_id' => $this->setup->operator->id,
        'vehicle_category_id' => $this->setup->car->id,
        'amount' => '50.00',
        'status' => 'paid',
        'plate_number' => 'JASHORE-GA 11-2222',
        'plate_source' => 'manual',
        'print_count' => 1,
        'issued_at' => now()->toDateTimeString(),
    ];

    $this->postJson('/api/sync/push', pushPayload('shift_sessions', [$sessionRow]), $headers)->assertOk();
    $this->postJson('/api/sync/push', pushPayload('toll_transactions', [$ticketRow]), $headers)->assertOk()->assertJson(['stored' => 1]);
    $this->postJson('/api/sync/push', pushPayload('toll_transactions', [$ticketRow]), $headers)->assertOk();

    $stored = TollTransaction::query()->sole();

    expect($stored->booth_id)->toBe($this->setup->booth->id)
        ->and($stored->synced_at)->not->toBeNull()
        ->and(Vehicle::query()->where('plate_number', 'JASHORE-GA 11-2222')->value('pass_count'))->toBe(1);
});

it('serves master data to booths', function () {
    $this->getJson('/api/sync/master?booth_code='.config('toll.booth_code'), ['Authorization' => 'Bearer secret-token'])
        ->assertOk()
        ->assertJsonPath('data.vehicle_categories.0.code', 'CAR')
        ->assertJsonStructure(['data' => ['booths', 'shifts', 'users', 'vehicle_categories', 'toll_rates', 'settings']]);
});
