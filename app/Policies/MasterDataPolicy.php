<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Toll;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for master data: managers can see it, `$manageRole` can change it,
 * and only where master data is editable (central server or stand-alone booth).
 */
abstract class MasterDataPolicy
{
    protected UserRole $viewRole = UserRole::PlazaManager;

    protected UserRole $manageRole = UserRole::PlazaManager;

    public function viewAny(User $user): bool
    {
        return $user->isAtLeast($this->viewRole);
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
        return $user->isAtLeast($this->manageRole) && Toll::canEditMasterData();
    }
}
