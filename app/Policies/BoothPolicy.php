<?php

namespace App\Policies;

use App\Enums\UserRole;

class BoothPolicy extends MasterDataPolicy
{
    protected UserRole $manageRole = UserRole::SuperAdmin;
}
