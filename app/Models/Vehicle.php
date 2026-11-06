<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['plate_number', 'vehicle_category_id', 'owner_name', 'owner_phone', 'pass_count', 'first_seen_at', 'last_seen_at'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TollTransaction::class, 'plate_number', 'plate_number');
    }
}
