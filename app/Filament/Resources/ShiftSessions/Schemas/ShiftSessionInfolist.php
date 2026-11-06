<?php

namespace App\Filament\Resources\ShiftSessions\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShiftSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (string $name, string $label): TextEntry => TextEntry::make($name)
            ->label($label)
            ->formatStateUsing(fn ($state): string => Money::format($state))
            ->placeholder('—');

        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Shift'))
                    ->columns(2)
                    ->components([
                        TextEntry::make('shift.name')->label('Shift')->weight('bold'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('user.name')->label('Operator'),
                        TextEntry::make('booth.name')->label('Booth'),
                        TextEntry::make('opened_at')->label('Opened at')->dateTime('d M Y, h:i A'),
                        TextEntry::make('closed_at')->label('Closed at')->dateTime('d M Y, h:i A')->placeholder('—'),
                    ]),
                Section::make(__('Cash reconciliation'))
                    ->columns(2)
                    ->components([
                        $money('opening_cash', 'Opening cash'),
                        TextEntry::make('transaction_count')->label('Vehicles')->placeholder('—'),
                        $money('expected_cash', 'Expected cash'),
                        $money('declared_cash', 'Declared cash'),
                        $money('cash_difference', 'Difference')
                            ->color(fn ($state): string => (float) $state < 0 ? 'danger' : ((float) $state > 0 ? 'warning' : 'success'))
                            ->weight('bold'),
                        TextEntry::make('notes')->label('Notes')->placeholder('—'),
                    ]),
            ]);
    }
}
