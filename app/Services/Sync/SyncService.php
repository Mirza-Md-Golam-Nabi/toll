<?php

namespace App\Services\Sync;

use App\Models\Booth;
use App\Services\RateService;
use App\Support\Settings;
use App\Support\Toll;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Booth side of the offline sync: pushes unsynced operational data to the central
 * server and pulls master data back. Safe to run every minute.
 */
class SyncService
{
    private const STATUS_KEY = 'toll.sync.status';

    public function run(): array
    {
        if (! Toll::syncEnabled()) {
            return ['ok' => false, 'message' => 'Sync is not configured.'];
        }

        try {
            $pushed = $this->push();
            $pulled = $this->pull();
            $this->rememberStatus(true, null);

            return ['ok' => true, 'pushed' => $pushed, 'pulled' => $pulled];
        } catch (Throwable $e) {
            $this->rememberStatus(false, $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, int> records pushed per table */
    public function push(): array
    {
        $counts = [];
        $batch = (int) config('toll.sync.batch_size');

        foreach (SyncRegistry::PUSH as $table => $modelClass) {
            $counts[$table] = 0;

            do {
                $records = $modelClass::query()->unsynced()->limit($batch)->get();

                if ($records->isEmpty()) {
                    break;
                }

                $this->client()->post('push', [
                    'booth_code' => config('toll.booth_code'),
                    'table' => $table,
                    'records' => $records->map->toSyncPayload()->all(),
                ])->throw();

                $modelClass::markSynced($records->modelKeys());
                $counts[$table] += $records->count();
            } while ($records->count() === $batch);
        }

        return $counts;
    }

    /** @return array<string, int> records received per table */
    public function pull(): array
    {
        $data = $this->client()->get('master', ['booth_code' => config('toll.booth_code')])->throw()->json('data', []);

        DB::transaction(function () use ($data): void {
            foreach (SyncRegistry::PULL as $table => $modelClass) {
                SyncRegistry::upsert($modelClass, $data[$table] ?? []);
            }
        });

        RateService::flush();
        Settings::flush();
        Booth::query()->where('code', config('toll.booth_code'))->update(['last_synced_at' => now()]);

        return array_map(fn ($rows) => count($rows), $data);
    }

    public function pendingCount(): int
    {
        return collect(SyncRegistry::PUSH)->sum(fn (string $modelClass) => $modelClass::query()->unsynced()->count());
    }

    /** @return array{ok: ?bool, at: ?string, error: ?string} */
    public function status(): array
    {
        return Cache::get(self::STATUS_KEY, ['ok' => null, 'at' => null, 'error' => null]);
    }

    private function rememberStatus(bool $ok, ?string $error): void
    {
        $previous = $this->status();

        Cache::forever(self::STATUS_KEY, [
            'ok' => $ok,
            'at' => $ok ? now()->toIso8601String() : $previous['at'],
            'error' => $error,
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('toll.sync.central_url'), '/').'/api/sync/')
            ->withToken(config('toll.sync.token'))
            ->acceptJson()
            ->timeout((int) config('toll.sync.timeout'))
            ->retry(2, 500, throw: false);
    }
}
