<?php

namespace App\Filament\Resources\Concerns;

use App\Support\Toll;
use Illuminate\Database\Eloquent\Builder;

/**
 * Booth operators only ever see their own records (by `user_id`); managers see everything.
 */
trait ScopesRecordsToOperator
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Toll::user();

        return $user?->isOperator()
            ? $query->where($query->qualifyColumn('user_id'), $user->id)
            : $query;
    }
}
