<?php

namespace App\Policies;

use App\Enums\Permission;

class ShiftPolicy extends MasterDataPolicy
{
    protected Permission $viewPermission = Permission::ViewShifts;

    protected Permission $managePermission = Permission::ManageShifts;
}
