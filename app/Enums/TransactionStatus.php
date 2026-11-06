<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionStatus: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case Paid = 'paid';
    case Exempt = 'exempt';
    case Voided = 'voided';

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Exempt => 'info',
            self::Voided => 'danger',
        };
    }

    /** Statuses that count as a real vehicle pass. */
    public static function counted(): array
    {
        return [self::Paid->value, self::Exempt->value];
    }
}
