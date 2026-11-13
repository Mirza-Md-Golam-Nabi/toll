<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Toll;

/**
 * Only the Super Admin maintains roles. The three roles are fixed (never created or deleted)
 * and the Super Admin role always keeps every permission, so it cannot be edited.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Role $role): bool
    {
        return $user->isSuperAdmin() && ! $role->isSuperAdmin() && Toll::canEditMasterData();
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
