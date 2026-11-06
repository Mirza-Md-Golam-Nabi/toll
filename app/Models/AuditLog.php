<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id', 'booth_id', 'event', 'auditable_type', 'auditable_id', 'old_values',
    'new_values', 'reason', 'approved_by', 'ip_address', 'synced_at', 'created_at',
])]
class AuditLog extends Model
{
    use Syncable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOverrides(Builder $query): Builder
    {
        return $query->whereIn('event', AuditEvent::overrides());
    }

    /** Short model name for display, e.g. "TollTransaction". */
    public function getSubjectAttribute(): ?string
    {
        return $this->auditable_type ? class_basename($this->auditable_type) : null;
    }
}
