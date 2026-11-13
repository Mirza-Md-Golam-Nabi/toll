<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\MobileDevices\MobileDeviceResource;
use App\Filament\Resources\MobileDevices\Pages\ManageMobileDevices;
use App\Models\AuditLog;
use App\Models\MobileDevice;
use App\Models\ShiftSession;
use App\Models\TollRate;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Mobile\MobileDeviceService;
use App\Services\RolePermissionService;
use App\Support\MobileApp;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    config(['toll.mode' => 'central']);
    $this->setup = tollSetup();
});

/** Signs the operator in on a fresh phone and returns the device with its auth header. */
function signedInPhone(User $operator): object
{
    ['device' => $device, 'token' => $token] = app(MobileDeviceService::class)->signIn($operator, (string) Str::uuid(), 'Test phone');

    return (object) ['device' => $device, 'headers' => ['Authorization' => 'Bearer '.$token]];
}

/** @return array<string, mixed> */
function phoneSession(array $overrides = []): array
{
    return array_merge([
        'id' => (string) Str::uuid(),
        'shift_id' => test()->setup->shift->id,
        'status' => 'open',
        'opened_at' => now()->subHour()->toDateTimeString(),
        'opening_cash' => 500,
    ], $overrides);
}

/** @return array<string, mixed> */
function phoneTicket(MobileDevice $device, string $sessionId, int $number, array $overrides = []): array
{
    return array_merge([
        'id' => (string) Str::uuid(),
        'ticket_no' => sprintf('%s-%s-%06d', $device->ticketPrefix(), now()->format('ymd'), $number),
        'shift_session_id' => $sessionId,
        'vehicle_category_id' => test()->setup->car->id,
        'amount' => '50.00',
        'plate_number' => null,
        'print_count' => 1,
        'issued_at' => now()->subMinutes(30)->toDateTimeString(),
    ], $overrides);
}

it('signs an operator in with the mobile number and returns everything needed offline', function () {
    $deviceUuid = (string) Str::uuid();
    TollRate::query()->create(['vehicle_category_id' => $this->setup->car->id, 'amount' => 60, 'effective_from' => now()->addWeek()]);

    $response = $this->postJson('/api/mobile/login', [
        'phone' => $this->setup->operator->phone,
        'password' => User::DEFAULT_PIN,
        'device_uuid' => $deviceUuid,
        'device_name' => 'Samsung A15',
    ])->assertOk();

    $device = MobileDevice::query()->sole();

    expect($response->json('token'))->toHaveLength(64)
        ->and($device->token_hash)->toBe(hash('sha256', $response->json('token')))
        ->and($device->code)->toBe('M1')
        ->and($device->booth_id)->toBe($this->setup->booth->id);

    $response
        ->assertJsonPath('data.device.ticket_prefix', $this->setup->booth->code.'-M1')
        ->assertJsonPath('data.user.id', $this->setup->operator->id)
        ->assertJsonPath('data.categories.0.code', 'CAR')
        ->assertJsonPath('data.categories.0.rates.0.amount', '50.00')
        ->assertJsonPath('data.categories.0.rates.1.amount', '60.00')
        ->assertJsonPath('data.ticket_sequence.last', 0)
        ->assertJsonStructure(['data' => ['booth', 'shifts', 'settings' => ['bridge_name_en', 'receipt_header']]]);

    expect(AuditLog::query()->where('event', AuditEvent::Login)->where('auditable_id', $device->id)->exists())->toBeTrue();
});

it('gives each phone of a booth its own code and keeps it on the next sign in', function () {
    $first = signedInPhone($this->setup->operator)->device;
    $second = signedInPhone(User::factory()->operator($this->setup->booth)->create())->device;

    app(MobileDeviceService::class)->signIn($this->setup->operator, $first->device_uuid);

    expect([$first->code, $second->code])->toBe(['M1', 'M2'])
        ->and($first->fresh()->code)->toBe('M1')
        ->and(MobileDevice::query()->count())->toBe(2);
});

it('refuses wrong PINs, users without a booth and blocked phones', function () {
    $login = fn (array $overrides = []) => $this->postJson('/api/mobile/login', array_merge([
        'phone' => $this->setup->operator->phone,
        'password' => User::DEFAULT_PIN,
        'device_uuid' => (string) Str::uuid(),
    ], $overrides));

    $login(['password' => '9999'])->assertJsonValidationErrors('phone');
    $login(['phone' => $this->setup->manager->phone])->assertOk();

    $manager = User::factory()->manager()->create();
    $login(['phone' => $manager->phone])->assertUnprocessable()->assertJsonPath('message', __('No booth is assigned to this user.'));

    $blocked = MobileDevice::factory()->blocked()->create(['booth_id' => $this->setup->booth->id]);
    $login(['device_uuid' => $blocked->device_uuid])->assertUnprocessable();
});

it('answers the reachability probe without a token', function () {
    $this->getJson('/api/mobile/ping')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['server_time']);
});

it('is only available on the central server', function () {
    config(['toll.mode' => 'booth']);

    $this->getJson('/api/mobile/ping')->assertNotFound();

    $this->postJson('/api/mobile/login', [
        'phone' => $this->setup->operator->phone,
        'password' => User::DEFAULT_PIN,
        'device_uuid' => (string) Str::uuid(),
    ])->assertNotFound();
});

it('rejects missing, signed-out and blocked tokens', function () {
    $this->getJson('/api/mobile/bootstrap')->assertUnauthorized();

    $phone = signedInPhone($this->setup->operator);
    $this->getJson('/api/mobile/bootstrap', $phone->headers)->assertOk();

    app(MobileDeviceService::class)->block($phone->device);
    $this->getJson('/api/mobile/bootstrap', $phone->headers)->assertUnauthorized();

    $other = signedInPhone($this->setup->operator);
    $this->postJson('/api/mobile/logout', [], $other->headers)->assertOk();
    $this->getJson('/api/mobile/bootstrap', $other->headers)->assertUnauthorized();
});

it('tells the app whether the user may see the shift summary', function () {
    $phone = signedInPhone($this->setup->operator);

    $this->getJson('/api/mobile/bootstrap', $phone->headers)->assertJsonPath('data.user.can_view_summary', true);

    app(RolePermissionService::class)->update(UserRole::BoothOperator, [Permission::CollectToll]);

    $this->getJson('/api/mobile/bootstrap', $phone->headers)->assertJsonPath('data.user.can_view_summary', false);
});

it('stores offline shifts and tickets idempotently under the phone, its booth and operator', function () {
    $phone = signedInPhone($this->setup->operator);
    $session = phoneSession();
    $tickets = [
        phoneTicket($phone->device, $session['id'], 1, ['plate_number' => 'dhaka metro-ga 11-2222']),
        phoneTicket($phone->device, $session['id'], 2),
    ];
    $payload = ['sessions' => [$session], 'tickets' => $tickets];

    $this->postJson('/api/mobile/push', $payload, $phone->headers)
        ->assertOk()
        ->assertJson(['sessions' => [$session['id']], 'tickets' => array_column($tickets, 'id'), 'rejected' => []]);
    $this->postJson('/api/mobile/push', $payload, $phone->headers)->assertOk()->assertJsonCount(2, 'tickets');

    $stored = TollTransaction::query()->orderBy('ticket_no')->get();
    $storedSession = ShiftSession::query()->sole();

    expect($stored)->toHaveCount(2)
        ->and($stored->pluck('booth_id')->unique()->all())->toBe([$this->setup->booth->id])
        ->and($stored->pluck('user_id')->unique()->all())->toBe([$this->setup->operator->id])
        ->and($stored->pluck('mobile_device_id')->unique()->all())->toBe([$phone->device->id])
        ->and($stored->first()->plate_number)->toBe('DHAKA METRO-GA 11-2222')
        ->and($storedSession->mobile_device_id)->toBe($phone->device->id)
        ->and($storedSession->status)->toBe(ShiftStatus::Open)
        ->and(Vehicle::query()->where('plate_number', 'DHAKA METRO-GA 11-2222')->value('pass_count'))->toBe(1)
        ->and(AuditLog::query()->where('event', AuditEvent::TicketIssued)->count())->toBe(2)
        ->and($phone->device->fresh()->last_synced_at)->not->toBeNull();

    $this->getJson('/api/mobile/bootstrap', $phone->headers)->assertJsonPath('data.ticket_sequence.last', 2);
});

it('reconciles a shift closed offline from the tickets stored on the server', function () {
    $phone = signedInPhone($this->setup->operator);
    $session = phoneSession();

    $this->postJson('/api/mobile/push', [
        'sessions' => [$session],
        'tickets' => [phoneTicket($phone->device, $session['id'], 1)],
    ], $phone->headers)->assertOk();

    // The closed shift arrives together with a late ticket.
    $this->postJson('/api/mobile/push', [
        'sessions' => [array_merge($session, ['status' => 'closed', 'closed_at' => now()->toDateTimeString(), 'declared_cash' => 590, 'notes' => 'Short by 10'])],
        'tickets' => [phoneTicket($phone->device, $session['id'], 2)],
    ], $phone->headers)->assertOk();

    $stored = ShiftSession::query()->sole();

    expect($stored->status)->toBe(ShiftStatus::Closed)
        ->and((float) $stored->expected_cash)->toBe(600.0)
        ->and((float) $stored->cash_difference)->toBe(-10.0)
        ->and($stored->transaction_count)->toBe(2)
        ->and(AuditLog::query()->where('event', AuditEvent::ShiftClosed)->count())->toBe(1);
});

it('records exempt passes at zero and keeps reprint counts', function () {
    $phone = signedInPhone($this->setup->operator);
    $ambulance = VehicleCategory::factory()->exempt()->withRate(0)->create();
    $session = phoneSession();
    $ticket = phoneTicket($phone->device, $session['id'], 1, ['vehicle_category_id' => $ambulance->id, 'amount' => '99.00', 'exempt_reason' => 'Patient']);

    $this->postJson('/api/mobile/push', ['sessions' => [$session], 'tickets' => [$ticket]], $phone->headers)->assertOk();
    $this->postJson('/api/mobile/push', ['sessions' => [], 'tickets' => [array_merge($ticket, ['print_count' => 2])]], $phone->headers)->assertOk();

    $stored = TollTransaction::query()->sole();

    expect($stored->status)->toBe(TransactionStatus::Exempt)
        ->and((float) $stored->amount)->toBe(0.0)
        ->and($stored->exempt_reason)->toBe('Patient')
        ->and($stored->print_count)->toBe(2)
        ->and(AuditLog::query()->where('event', AuditEvent::TicketReprinted)->count())->toBe(1);
});

it('rejects tickets of another phone, foreign ticket numbers and duplicates without losing the rest', function () {
    $phone = signedInPhone($this->setup->operator);
    $otherPhone = signedInPhone(User::factory()->operator($this->setup->booth)->create());
    $session = phoneSession();
    $otherSession = phoneSession();

    $this->postJson('/api/mobile/push', ['sessions' => [$otherSession], 'tickets' => []], $otherPhone->headers)->assertOk();

    $good = phoneTicket($phone->device, $session['id'], 1);
    $foreignSession = phoneTicket($phone->device, $otherSession['id'], 2);
    $foreignNumber = phoneTicket($otherPhone->device, $session['id'], 3);

    $response = $this->postJson('/api/mobile/push', [
        'sessions' => [$session, $otherSession],
        'tickets' => [$good, $foreignSession, $foreignNumber],
    ], $phone->headers)->assertOk();

    expect($response->json('tickets'))->toBe([$good['id']])
        ->and(array_column($response->json('rejected'), 'id'))->toBe([$otherSession['id'], $foreignSession['id'], $foreignNumber['id']])
        ->and(TollTransaction::query()->pluck('id')->all())->toBe([$good['id']]);

    $duplicate = phoneTicket($phone->device, $session['id'], 1);
    $this->postJson('/api/mobile/push', ['sessions' => [], 'tickets' => [$duplicate]], $phone->headers)
        ->assertJsonPath('rejected.0.reason', 'This ticket number is already used.');
});

it('validates the pushed payload', function () {
    $phone = signedInPhone($this->setup->operator);

    $this->postJson('/api/mobile/push', [
        'sessions' => [phoneSession(['status' => 'closed'])],
        'tickets' => [['id' => 'not-a-uuid']],
    ], $phone->headers)->assertJsonValidationErrors([
        'sessions.0.closed_at',
        'sessions.0.declared_cash',
        'tickets.0.id',
        'tickets.0.ticket_no',
        'tickets.0.issued_at',
    ]);
});

it('lists phones for managers on the central server and blocks them', function () {
    $phone = signedInPhone($this->setup->operator);

    $this->actingAs($this->setup->manager)
        ->get(MobileDeviceResource::getUrl())
        ->assertOk()
        ->assertSee($phone->device->code);

    Livewire::test(ManageMobileDevices::class)
        ->callAction(TestAction::make('block')->table($phone->device));

    expect($phone->device->fresh())->is_active->toBeFalse()->token_hash->toBeNull();

    $this->actingAs($this->setup->operator)->get(MobileDeviceResource::getUrl())->assertForbidden();
});

it('serves the installable app shell', function () {
    $this->get('/mobile/app')
        ->assertOk()
        ->assertSee('/mobile/manifest.webmanifest')
        ->assertSee('/mobile/sync-core.js?v='.MobileApp::VERSION)
        ->assertSee('/mobile/app.js?v='.MobileApp::VERSION);

    expect(file_get_contents(public_path('mobile/sw.js')))
        ->toContain("'toll-mobile-".MobileApp::VERSION."'")
        ->toContain("ASSET_VERSION = '".MobileApp::VERSION."'");
});
