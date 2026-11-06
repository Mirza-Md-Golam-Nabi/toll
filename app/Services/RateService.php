<?php

namespace App\Services;

use App\Models\TollRate;
use App\Models\VehicleCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the toll amount for a category. Active categories with their current
 * rate are loaded in ONE query and cached until the next scheduled rate change.
 */
class RateService
{
    private const CACHE_KEY = 'toll.rates.current';

    /** @return Collection<int, VehicleCategory> each with `current_amount`. */
    public function activeCategories(): Collection
    {
        // Raw rows are cached (objects are not unserialized from cache) and hydrated without queries.
        $rows = Cache::remember(self::CACHE_KEY, $this->cacheUntil(), fn (): array => VehicleCategory::query()
            ->select('vehicle_categories.*')
            ->active()
            ->ordered()
            ->withCurrentAmount()
            ->toBase()
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all());

        return VehicleCategory::hydrate($rows);
    }

    public function amountFor(VehicleCategory $category, ?CarbonInterface $at = null): string
    {
        if ($category->is_exempt) {
            return '0.00';
        }

        $amount = $at === null
            ? $this->activeCategories()->firstWhere('id', $category->id)?->current_amount
            : TollRate::query()
                ->where('vehicle_category_id', $category->id)
                ->where('effective_from', '<=', $at)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->value('amount');

        return number_format((float) ($amount ?? 0), 2, '.', '');
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Expire the cache exactly when a future rate becomes effective (max 10 minutes). */
    private function cacheUntil(): CarbonInterface
    {
        $fallback = now()->addMinutes(10);
        $next = TollRate::query()->where('effective_from', '>', now())->min('effective_from');

        return $next ? min($fallback, now()->parse($next)) : $fallback;
    }
}
