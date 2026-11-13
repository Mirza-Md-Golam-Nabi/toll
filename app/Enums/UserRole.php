<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Services\RolePermissionService;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case SuperAdmin = 'super_admin';
    case PlazaManager = 'plaza_manager';
    case BoothOperator = 'booth_operator';

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::PlazaManager => 'warning',
            self::BoothOperator => 'info',
        };
    }

    /** The Super Admin holds every permission; other roles hold what the Super Admin granted. */
    public function hasPermission(Permission $permission): bool
    {
        return app(RolePermissionService::class)->roleHas($this, $permission);
    }
}
