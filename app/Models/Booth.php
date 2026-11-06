<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'location', 'printer_name', 'is_active', 'last_synced_at'])]
class Booth extends Model
{
    use Auditable, HasActiveScope, HasFactory;

    protected array $auditExclude = ['last_synced_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shiftSessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TollTransaction::class);
    }

    /** The booth this installation runs as (booth mode). Memoised in the container, only once found. */
    public static function current(): ?self
    {
        if (app()->bound('toll.booth')) {
            return app('toll.booth');
        }

        $booth = static::query()->where('code', config('toll.booth_code'))->first();

        if ($booth) {
            app()->instance('toll.booth', $booth);
        }

        return $booth;
    }
}
