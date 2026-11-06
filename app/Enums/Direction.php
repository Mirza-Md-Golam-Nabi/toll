<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum Direction: string implements HasLabel
{
    use EnumHelpers;

    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
