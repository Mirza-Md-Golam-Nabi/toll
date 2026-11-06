<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditEvent: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Login = 'login';
    case Logout = 'logout';
    case ShiftOpened = 'shift_opened';
    case ShiftClosed = 'shift_closed';
    case TicketIssued = 'ticket_issued';
    case TicketVoided = 'ticket_voided';
    case CategoryCorrected = 'category_corrected';
    case TicketReprinted = 'ticket_reprinted';

    public function getColor(): string
    {
        return match ($this) {
            self::Created, self::TicketIssued, self::ShiftOpened => 'success',
            self::Updated, self::CategoryCorrected, self::TicketReprinted => 'warning',
            self::Deleted, self::TicketVoided => 'danger',
            default => 'gray',
        };
    }

    /** Events that represent a manual override of a ticket. */
    public static function overrides(): array
    {
        return [self::TicketVoided->value, self::CategoryCorrected->value, self::TicketReprinted->value];
    }
}
