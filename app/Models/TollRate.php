<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\RateService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_category_id', 'amount', 'effective_from', 'note', 'created_by'])]
class TollRate extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::creating(function (self $rate): void {
            $rate->created_by ??= auth()->id();
        });
        static::saved(fn () => RateService::flush());
        static::deleted(fn () => RateService::flush());
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
