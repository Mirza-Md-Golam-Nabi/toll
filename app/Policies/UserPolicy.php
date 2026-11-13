<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A role granted "Add / edit users" still cannot touch a Super Admin account —
 * only a Super Admin can, so nobody can promote themselves above their role.
 */
class UserPolicy extends MasterDataPolicy
{
    protected Permission $viewPermission = Permission::ViewUsers;

    protected Permission $managePermission = Permission::ManageUsers;

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->canTouch($user, $model);
    }

    /** Nobody deletes their own account. */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->canTouch($user, $model) && ! $user->is($model);
    }

    private function canTouch(User $user, Model $model): bool
    {
        return $user->isSuperAdmin() || ! $model->isSuperAdmin();
    }
}
