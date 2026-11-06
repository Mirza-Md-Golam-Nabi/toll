<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Immutable filter set shared by the report page, dashboard widgets and CSV export.
 */
final readonly class ReportFilters
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        public ?int $boothId = null,
        public ?int $userId = null,
        public ?int $categoryId = null,
        public ?int $shiftId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            from: CarbonImmutable::parse($data['from'] ?? today())->startOfDay(),
            until: CarbonImmutable::parse($data['until'] ?? today())->endOfDay(),
            boothId: self::int($data['booth_id'] ?? null),
            userId: self::int($data['user_id'] ?? null),
            categoryId: self::int($data['vehicle_category_id'] ?? null),
            shiftId: self::int($data['shift_id'] ?? null),
        );
    }

    public static function today(): self
    {
        return self::fromArray([]);
    }

    public static function lastDays(int $days): self
    {
        return self::fromArray(['from' => today()->subDays($days - 1), 'until' => today()]);
    }

    /** Apply the filters to a toll_transactions query. */
    public function apply(Builder $query): Builder
    {
        $table = 'toll_transactions';

        return $query
            ->whereBetween("{$table}.issued_at", [$this->from, $this->until])
            ->when($this->boothId, fn (Builder $q, int $id) => $q->where("{$table}.booth_id", $id))
            ->when($this->userId, fn (Builder $q, int $id) => $q->where("{$table}.user_id", $id))
            ->when($this->categoryId, fn (Builder $q, int $id) => $q->where("{$table}.vehicle_category_id", $id))
            ->when($this->shiftId, fn (Builder $q, int $id) => $q->whereIn(
                "{$table}.shift_session_id",
                fn ($sub) => $sub->select('id')->from('shift_sessions')->where('shift_id', $id),
            ));
    }

    public function cacheKey(): string
    {
        return md5(serialize([$this->from->toIso8601String(), $this->until->toIso8601String(), $this->boothId, $this->userId, $this->categoryId, $this->shiftId]));
    }

    private static function int(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
