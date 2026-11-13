<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\Permission;
use App\Support\Toll;

trait VisibleToManagers
{
    public static function canView(): bool
    {
        return Toll::userCan(Permission::ViewDashboardStats);
    }
}
