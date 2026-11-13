<?php

namespace App\Services\Sync;

use App\Models\Booth;
use App\Models\TollTransaction;
use App\Services\RolePermissionService;
use App\Services\VehicleRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Central side of the sync: idempotently stores what booths push and serves master data.
 */
class SyncIngestService
{
    public function __construct(
        private VehicleRegistry $vehicles,
        private RolePermissionService $rolePermissions,
    ) {}

    public function ingest(Booth $booth, string $table, array $records): int
    {
        $modelClass = SyncRegistry::PUSH[$table] ?? throw new InvalidArgumentException("Unknown sync table [{$table}].");

        // Never trust the booth id sent in the payload.
        $rows = array_map(fn (array $row) => array_merge($row, ['booth_id' => $booth->id, 'synced_at' => now()]), $records);

        DB::transaction(function () use ($modelClass, $rows): void {
            $newTransactions = $modelClass === TollTransaction::class ? $this->newRows($rows) : [];

            SyncRegistry::upsert($modelClass, $rows);

            $this->vehicles->recordPasses($newTransactions);
        });

        $booth->update(['last_synced_at' => now()]);

        return count($rows);
    }

    /** @return array<string, array> table => raw rows (incl. hashed secrets), plus role => permission names */
    public function masterData(): array
    {
        return collect(SyncRegistry::PULL)
            ->map(fn (string $modelClass) => $modelClass::query()->toBase()->get()->map(fn ($row) => (array) $row)->all())
            ->put(SyncRegistry::ROLE_PERMISSIONS, $this->rolePermissions->export())
            ->all();
    }

    /** Rows whose ids are not stored yet — only those count as new vehicle passes. */
    private function newRows(array $rows): array
    {
        $existing = TollTransaction::query()->whereKey(array_column($rows, 'id'))->pluck('id')->flip();

        return array_values(array_filter($rows, fn (array $row) => ! isset($existing[$row['id']])));
    }
}
