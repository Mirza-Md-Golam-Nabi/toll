<?php

namespace App\Policies;

use App\Enums\Permission;

class VehicleCategoryPolicy extends MasterDataPolicy
{
    protected Permission $viewPermission = Permission::ViewVehicleCategories;

    protected Permission $managePermission = Permission::ManageVehicleCategories;
}
