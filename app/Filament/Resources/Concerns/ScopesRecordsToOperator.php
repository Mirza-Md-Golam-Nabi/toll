<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\Permission;
use App\Support\Toll;
use Illuminate\Database\Eloquent\Builder;

/**
 * Users without "see every operator's records" only see their own records (by `user_id`).
 */
trait ScopesRecordsToOperator
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Toll::user();

        return $user && ! $user->hasPermission(Permission::ViewAllRecords)
            ? $query->where($query->qualifyColumn('user_id'), $user->id)
            : $query;
    }
}
