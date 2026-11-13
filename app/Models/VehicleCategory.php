<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use App\Models\Concerns\HasSortOrder;
use App\Services\RateService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name_bn', 'name_en', 'shortcut_key', 'color', 'icon', 'is_exempt', 'is_active', 'sort_order'])]
class VehicleCategory extends Model
{
    use Auditable, HasActiveScope, HasFactory, HasSortOrder;

    protected static function booted(): void
    {
        static::saved(fn () => RateService::flush());
        static::deleted(fn () => RateService::flush());
    }

    protected function casts(): array
    {
        return [
            'is_exempt' => 'boolean',
            'is_active' => 'boolean',
            'current_amount' => 'decimal:2',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TollRate::class);
    }

    /** Rate in force right now (latest effective_from <= now). */
    public function currentRate(): HasOne
    {
        return $this->hasOne(TollRate::class)->ofMany(
            ['effective_from' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('effective_from', '<=', now()),
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TollTransaction::class);
    }

    /** Adds a `current_amount` column via a correlated sub-select (no N+1). */
    public function scopeWithCurrentAmount(Builder $query): Builder
    {
        return $query->addSelect([
            'current_amount' => TollRate::query()
                ->select('amount')
                ->whereColumn('vehicle_category_id', 'vehicle_categories.id')
                ->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->limit(1),
        ]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Optional and unique: stored trimmed and upper-case, a blank code is stored as NULL. */
    public static function normalizeCode(?string $code): ?string
    {
        return filled($code) ? mb_strtoupper(trim($code)) : null;
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeCode($value));
    }

    /** Optional: a blank English name is stored as NULL. */
    protected function nameEn(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? trim($value) : null);
    }

    /** Localised display name; falls back to the (required) Bangla name. */
    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'bn' ? $this->name_bn : $this->english_name;
    }

    /** Latin-script name for receipts and English screens: English name, else code, else Bangla name. */
    public function getEnglishNameAttribute(): string
    {
        return $this->name_en ?: ($this->code ?: $this->name_bn);
    }

    /**
     * Select options for filters and reports, in collection-screen order.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return self::query()->ordered()->get(['id', 'code', 'name_bn', 'name_en'])
            ->mapWithKeys(fn (self $category): array => [$category->id => $category->display_name])
            ->all();
    }
}
