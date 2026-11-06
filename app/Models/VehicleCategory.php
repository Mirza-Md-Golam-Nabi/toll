<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use App\Services\RateService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name_bn', 'name_en', 'shortcut_key', 'color', 'icon', 'is_exempt', 'is_active', 'sort_order'])]
class VehicleCategory extends Model
{
    use Auditable, HasActiveScope, HasFactory;

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

    /** Localised display name. */
    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'bn' ? $this->name_bn : $this->name_en;
    }
}
