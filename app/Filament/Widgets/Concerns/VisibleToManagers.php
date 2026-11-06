<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\UserRole;
use App\Support\Toll;

trait VisibleToManagers
{
    public static function canView(): bool
    {
        return Toll::userIsAtLeast(UserRole::PlazaManager);
    }
}
