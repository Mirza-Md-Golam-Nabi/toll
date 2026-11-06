<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Records created at a booth and pushed to the central server.
 * UUID keys make them collision-free across booths; `synced_at` tracks delivery.
 */
trait Syncable
{
    use HasUuids;

    /** Any local change (e.g. a void) must be pushed again. */
    public static function bootSyncable(): void
    {
        static::updating(function (self $model): void {
            if (! $model->isDirty('synced_at')) {
                $model->synced_at = null;
            }
        });
    }

    public function scopeUnsynced(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('synced_at'));
    }

    /** Mark many records as delivered with a single UPDATE. */
    public static function markSynced(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return static::query()->whereKey($ids)->update(['synced_at' => now()]);
    }

    /** Columns sent to the central server (everything except local sync state). */
    public function toSyncPayload(): array
    {
        return collect($this->getAttributes())->except('synced_at')->all();
    }
}
