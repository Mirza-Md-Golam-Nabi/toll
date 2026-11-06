<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the `vehicles` register up to date. Used on the booth (each ticket) and on the
 * central server (each synced batch) — always one bulk UPSERT, never a query per plate.
 */
class VehicleRegistry
{
    /**
     * @param  iterable<array{plate_number: ?string, vehicle_category_id: int, issued_at: mixed}>  $passes
     */
    public function recordPasses(iterable $passes): void
    {
        $now = now();

        $rows = collect($passes)
            ->filter(fn (array $pass) => filled($pass['plate_number']))
            ->groupBy('plate_number')
            ->map(function ($group, string $plate) use ($now): array {
                $latest = $group->sortBy('issued_at')->last();

                return [
                    'plate_number' => $plate,
                    'vehicle_category_id' => $latest['vehicle_category_id'],
                    'pass_count' => $group->count(),
                    'first_seen_at' => $group->min('issued_at'),
                    'last_seen_at' => $latest['issued_at'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        Vehicle::query()->upsert($rows, ['plate_number'], [
            'vehicle_category_id',
            'last_seen_at',
            'updated_at',
            'pass_count' => DB::raw('pass_count + VALUES(pass_count)'),
        ]);
    }
}
