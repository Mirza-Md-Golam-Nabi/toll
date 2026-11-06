<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PlateSource: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case None = 'none';
    case Manual = 'manual';
    case Anpr = 'anpr';

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Manual => 'warning',
            self::Anpr => 'success',
        };
    }
}
