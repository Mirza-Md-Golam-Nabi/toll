<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TollRatePolicy extends MasterDataPolicy
{
    /** Rates that already took effect are history and stay untouched. */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $model->effective_from->isFuture();
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $model->effective_from->isFuture();
    }
}
