<?php

namespace App\Policies;

use App\Enums\Permission;

class BoothPolicy extends MasterDataPolicy
{
    protected Permission $viewPermission = Permission::ViewBooths;

    protected Permission $managePermission = Permission::ManageBooths;
}
