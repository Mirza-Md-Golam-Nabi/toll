<?php

namespace App\Policies;

use App\Enums\AppMode;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Phones register themselves on first sign in; managers may only rename or block them,
 * and only on the central server where the phones connect.
 */
class MobileDevicePolicy extends MasterDataPolicy
{
    protected Permission $viewPermission = Permission::ViewMobileDevices;

    protected Permission $managePermission = Permission::ManageMobileDevices;

    public function viewAny(User $user): bool
    {
        return AppMode::isCentral() && parent::viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
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
