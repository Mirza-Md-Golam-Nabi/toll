<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Keeps `sort_order` a gap-free 1..n sequence.
 *
 * - Create without a position (blank / 0): appended at the end.
 * - Create at position P: rows at P and after move down by one.
 * - Edit from O to N: only the rows between N and O shift by one, in the opposite direction.
 * - Delete: rows after the removed one move up by one.
 *
 * Each case runs a single bulk UPDATE (query builder, so no per-row model events).
 */
trait HasSortOrder
{
    public static function bootHasSortOrder(): void
    {
        static::creating(fn (Model $model) => $model->insertIntoSortSequence());
        static::updating(fn (Model $model) => $model->moveWithinSortSequence());
        static::deleted(fn (Model $model) => $model->closeSortSequenceGap());
    }

    /** The position a newly created record takes by default (last + 1). */
    public static function nextSortOrder(): int
    {
        return (int) static::query()->max('sort_order') + 1;
    }

    protected function insertIntoSortSequence(): void
    {
        $position = (int) $this->sort_order;

        if ($position < 1) {
            $this->sort_order = static::nextSortOrder();

            return;
        }

        static::query()->where('sort_order', '>=', $position)->increment('sort_order');
    }

    protected function moveWithinSortSequence(): void
    {
        if (! $this->isDirty('sort_order')) {
            return;
        }

        $from = (int) $this->getOriginal('sort_order');
        $to = (int) $this->sort_order;

        if ($to < 1) {
            $this->sort_order = $from;

            return;
        }

        $siblings = static::query()->whereKeyNot($this->getKey());

        if ($from < 1) {
            $siblings->where('sort_order', '>=', $to)->increment('sort_order');
        } elseif ($to < $from) {
            $siblings->whereBetween('sort_order', [$to, $from - 1])->increment('sort_order');
        } else {
            $siblings->whereBetween('sort_order', [$from + 1, $to])->decrement('sort_order');
        }
    }

    protected function closeSortSequenceGap(): void
    {
        $position = (int) $this->sort_order;

        if ($position > 0) {
            static::query()->where('sort_order', '>', $position)->decrement('sort_order');
        }
    }
}
