<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\Booth;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\TollRate;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\VehicleCategory;

/**
 * The single list of what is synchronised and in which direction/order.
 * Order matters for foreign keys.
 */
final class SyncRegistry
{
    /** Booth -> central (operational data, UUID keys). */
    public const PUSH = [
        'shift_sessions' => ShiftSession::class,
        'toll_transactions' => TollTransaction::class,
        'audit_logs' => AuditLog::class,
    ];

    /** Central -> booth (master data, owned by central). */
    public const PULL = [
        'booths' => Booth::class,
        'shifts' => Shift::class,
        'users' => User::class,
        'vehicle_categories' => VehicleCategory::class,
        'toll_rates' => TollRate::class,
        'settings' => Setting::class,
    ];

    /** Key of the role => permission names map served with the master data. */
    public const ROLE_PERMISSIONS = 'role_permissions';

    /** Columns that the remote side may write for a model (fillable + key + timestamps). */
    public static function writableColumns(string $modelClass): array
    {
        $model = new $modelClass;

        return array_values(array_unique(array_merge(
            [$model->getKeyName()],
            $model->getFillable(),
            $model->usesTimestamps() ? array_filter([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()]) : ['created_at'],
            $modelClass === User::class ? ['password', 'remember_token', 'email_verified_at'] : [],
        )));
    }

    /** Filter raw rows to writable columns. */
    public static function clean(string $modelClass, array $rows): array
    {
        $allowed = array_flip(self::writableColumns($modelClass));

        return array_map(fn (array $row) => array_intersect_key($row, $allowed), $rows);
    }

    /** Upsert raw rows without firing model events (no audit / hashing side effects). */
    public static function upsert(string $modelClass, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $model = new $modelClass;
        $rows = self::clean($modelClass, $rows);
        $columns = array_keys($rows[0]);

        foreach (array_chunk($rows, 500) as $chunk) {
            $model->newQuery()->toBase()->upsert($chunk, [$model->getKeyName()], array_values(array_diff($columns, [$model->getKeyName()])));
        }
    }
}
