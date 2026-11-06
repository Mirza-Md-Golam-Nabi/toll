<?php

namespace App\Services;

use App\Models\Booth;
use App\Models\TicketSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Produces gap-free, per-booth, per-day ticket numbers: B01-261106-000123.
 * Row lock guarantees uniqueness even with concurrent requests.
 */
class TicketNumberGenerator
{
    public function next(Booth $booth, CarbonInterface $at): string
    {
        return DB::transaction(function () use ($booth, $at): string {
            $key = ['booth_id' => $booth->id, 'sequence_date' => $at->toDateString()];

            TicketSequence::query()->insertOrIgnore($key + ['last_number' => 0]);

            $sequence = TicketSequence::query()->where($key)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('%s-%s-%06d', $booth->code, $at->format('ymd'), $sequence->last_number);
        });
    }
}
