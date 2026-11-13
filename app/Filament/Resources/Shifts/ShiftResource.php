<?php

namespace App\Filament\Resources\Shifts;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShiftResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Name')->required()->maxLength(255),
                TimePicker::make('start_time')->label('Start time')->seconds(false)->required(),
                TimePicker::make('end_time')->label('End time')->seconds(false)->required(),
                Fields::sortOrder(Shift::class),
                Fields::active(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Name')->weight('semibold'),
                TextColumn::make('time_range')->label('Time')->badge()->color('info'),
                Columns::active(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShifts::route('/'),
        ];
    }
}
