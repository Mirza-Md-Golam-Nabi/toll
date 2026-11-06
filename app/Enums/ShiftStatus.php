<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ShiftStatus: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case Open = 'open';
    case Closed = 'closed';

    public function getColor(): string
    {
        return $this === self::Open ? 'success' : 'gray';
    }
}
