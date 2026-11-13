<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use Database\Factories\MobileDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Android phone running the offline collection app.
 * Its access token is stored only as a SHA-256 hash.
 */
#[Fillable(['device_uuid', 'booth_id', 'code', 'name', 'user_id', 'token_hash', 'is_active', 'last_seen_at', 'last_synced_at'])]
#[Hidden(['token_hash'])]
class MobileDevice extends Model
{
    /** @use HasFactory<MobileDeviceFactory> */
    use Auditable, HasActiveScope, HasFactory;

    protected array $auditExclude = ['token_hash', 'last_seen_at', 'last_synced_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shiftSessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TollTransaction::class);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /** Ticket number prefix for this phone, e.g. "B01-M2". */
    public function ticketPrefix(): string
    {
        return $this->booth->code.'-'.$this->code;
    }
}
