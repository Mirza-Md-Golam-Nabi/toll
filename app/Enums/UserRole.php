<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
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

    /** Roles whose PIN can approve overrides (void / category correction). */
    public function canApproveOverrides(): bool
    {
        return $this !== self::BoothOperator;
    }

    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 3,
            self::PlazaManager => 2,
            self::BoothOperator => 1,
        };
    }

    public function isAtLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }
}
