<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends MasterDataPolicy
{
    protected UserRole $viewRole = UserRole::SuperAdmin;

    protected UserRole $manageRole = UserRole::SuperAdmin;

    /** Nobody deletes their own account. */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $user->is($model);
    }
}
