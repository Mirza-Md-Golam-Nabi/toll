<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Toll;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for master data: `$viewPermission` lets a role see it, `$managePermission`
 * lets it change it, and only where master data is editable (central server or stand-alone booth).
 */
abstract class MasterDataPolicy
{
    protected Permission $viewPermission;

    protected Permission $managePermission;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->viewPermission);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function reorder(User $user): bool
    {
        return $this->canManage($user);
    }

    protected function canManage(User $user): bool
    {
        return $user->hasPermission($this->managePermission) && Toll::canEditMasterData();
    }
}
