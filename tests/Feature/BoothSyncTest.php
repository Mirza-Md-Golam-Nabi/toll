<?php

use App\Models\AuditLog;
use App\Models\ShiftSession;
use App\Models\TollTransaction;
use App\Models\VehicleCategory;
use App\Services\Sync\SyncIngestService;
use App\Services\Sync\SyncService;
use App\Services\TollTransactionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'toll.mode' => 'booth',
        'toll.sync.central_url' => 'http://central.test',
        'toll.sync.token' => 'secret-token',
    ]);

    $this->setup = tollSetup();
    openShift($this->setup);
    app(TollTransactionService::class)->issue($this->setup->operator, $this->setup->car);
});

/** What the central server would answer for /master: current master data with a renamed category. */
function centralMasterData(VehicleCategory $car): array
{
    $data = app(SyncIngestService::class)->masterData();
    $data['vehicle_categories'] = array_map(
        fn (array $row) => $row['id'] === $car->id ? ['name_en' => 'Sedan'] + $row : $row,
        $data['vehicle_categories'],
    );

    return $data;
}

it('pushes unsynced records in order and applies master data from central', function () {
    $master = centralMasterData($this->setup->car);

    Http::fake([
        'central.test/api/sync/push' => Http::response(['stored' => 1]),
        'central.test/api/sync/master*' => Http::response(['data' => $master]),
    ]);

    $result = app(SyncService::class)->run();

    expect($result['ok'])->toBeTrue()
        ->and($result['pushed']['shift_sessions'])->toBe(1)
        ->and($result['pushed']['toll_transactions'])->toBe(1)
        ->and(ShiftSession::query()->unsynced()->count())->toBe(0)
        ->and(TollTransaction::query()->unsynced()->count())->toBe(0)
        ->and(AuditLog::query()->unsynced()->count())->toBe(0)
        ->and($this->setup->car->fresh()->name_en)->toBe('Sedan')
        ->and(app(SyncService::class)->status()['ok'])->toBeTrue();

    $tables = collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_ends_with($request->url(), '/push'))
        ->map(fn (Request $request) => $request['table'])
        ->values()
        ->all();

    expect($tables)->toBe(['shift_sessions', 'toll_transactions', 'audit_logs']);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('keeps records unsynced and reports the error when central is unreachable', function () {
    Http::fake(['central.test/*' => Http::response('down', 503)]);

    $result = app(SyncService::class)->run();

    expect($result['ok'])->toBeFalse()
        ->and(TollTransaction::query()->unsynced()->count())->toBe(1)
        ->and(app(SyncService::class)->status()['error'])->not->toBeNull();
});

it('re-queues a ticket for sync after it is voided', function () {
    $ticket = TollTransaction::query()->sole();
    TollTransaction::markSynced([$ticket->id]);

    app(TollTransactionService::class)->void($ticket->fresh(), 'Mistake', $this->setup->manager, $this->setup->manager);

    expect($ticket->fresh()->synced_at)->toBeNull();
});

it('does not run without central configuration', function () {
    config(['toll.sync.central_url' => null]);

    expect(app(SyncService::class)->run()['ok'])->toBeFalse();
});
