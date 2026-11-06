<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\PlateSource;
use App\Enums\TransactionStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_no', 'booth_id', 'shift_session_id', 'user_id', 'vehicle_category_id', 'amount', 'status',
    'exempt_reason', 'plate_number', 'plate_source', 'plate_image', 'anpr_confidence', 'direction',
    'print_count', 'issued_at', 'voided_at', 'voided_by', 'approved_by', 'void_reason', 'synced_at',
])]
class TollTransaction extends Model
{
    use HasAuditLogs, HasFactory, Syncable;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'anpr_confidence' => 'decimal:2',
            'status' => TransactionStatus::class,
            'plate_source' => PlateSource::class,
            'direction' => Direction::class,
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function shiftSession(): BelongsTo
    {
        return $this->belongsTo(ShiftSession::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'plate_number', 'plate_number');
    }

    /** Only passes that count towards collection (paid + exempt). */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), TransactionStatus::counted());
    }

    /** Latest first; ticket number breaks ties within the same second. */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc($this->qualifyColumn('issued_at'))->orderByDesc($this->qualifyColumn('ticket_no'));
    }

    public function isVoided(): bool
    {
        return $this->status === TransactionStatus::Voided;
    }
}
