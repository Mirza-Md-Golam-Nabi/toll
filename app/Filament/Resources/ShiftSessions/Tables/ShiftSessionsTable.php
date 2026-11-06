<?php

namespace App\Filament\Resources\ShiftSessions\Tables;

use App\Enums\ShiftStatus;
use App\Filament\Actions\ShiftActions;
use App\Filament\Support\Columns;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShiftSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['shift:id,name', 'user:id,name', 'booth:id,code']))
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('shift.name')->label('Shift')->weight('semibold'),
                TextColumn::make('user.name')->label('Operator')->searchable(),
                TextColumn::make('booth.code')->label('Booth')->badge()->color('gray'),
                Columns::dateTime('opened_at', 'Opened at'),
                Columns::dateTime('closed_at', 'Closed at')->placeholder('—'),
                Columns::badge('status', 'Status'),
                TextColumn::make('transaction_count')->label('Vehicles')->alignCenter()->placeholder('—'),
                Columns::money('expected_cash', 'Expected cash'),
                Columns::money('declared_cash', 'Declared cash'),
                Columns::money('cash_difference', 'Difference')
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state < 0 => 'danger',
                        (float) $state > 0 => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ShiftStatus::class),
                SelectFilter::make('shift_id')->label('Shift')->relationship('shift', 'name'),
                SelectFilter::make('user_id')->label('Operator')->relationship('user', 'name')->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                ShiftActions::close(),
            ]);
    }
}
