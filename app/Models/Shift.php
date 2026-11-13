<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use App\Models\Concerns\HasSortOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'start_time', 'end_time', 'sort_order', 'is_active'])]
class Shift extends Model
{
    use Auditable, HasActiveScope, HasFactory, HasSortOrder;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class);
    }

    /** Whether the given time falls in this shift (handles overnight shifts like 22:00-06:00). */
    public function covers(CarbonInterface $time): bool
    {
        $now = $time->format('H:i:s');
        $start = $this->start_time;
        $end = $this->end_time;

        return $start <= $end
            ? $now >= $start && $now < $end
            : $now >= $start || $now < $end;
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }
}
