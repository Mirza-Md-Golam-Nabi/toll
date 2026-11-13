<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Vehicles are registered automatically from tickets; managers may only add owner details.
 */
class VehiclePolicy extends MasterDataPolicy
{
    protected Permission $viewPermission = Permission::ViewVehicles;

    protected Permission $managePermission = Permission::UpdateVehicles;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasPermission($this->managePermission);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
