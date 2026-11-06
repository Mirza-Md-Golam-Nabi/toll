<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'booth_id', 'shift_id', 'user_id', 'status', 'opened_at', 'closed_at', 'opening_cash',
    'expected_cash', 'declared_cash', 'cash_difference', 'transaction_count', 'notes', 'synced_at',
])]
class ShiftSession extends Model
{
    use HasAuditLogs, HasFactory, Syncable;

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'synced_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'declared_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TollTransaction::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::Open->value);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }
}
