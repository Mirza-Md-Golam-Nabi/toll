<?php

namespace App\Filament\Resources\Vehicles;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Filament\Support\Columns;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VehicleResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'plate_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('plate_number')->label('Plate number')->disabled(),
                TextInput::make('owner_name')->label('Owner name')->maxLength(255),
                TextInput::make('owner_phone')->label('Owner phone')->tel()->maxLength(20),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category:id,name_bn,name_en,color'))
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('plate_number')->label('Plate number')->searchable()->weight('bold')->fontFamily('mono')->copyable(),
                Columns::category(),
                TextColumn::make('owner_name')->label('Owner name')->searchable()->placeholder('—')
                    ->description(fn (Vehicle $record): ?string => $record->owner_phone),
                TextColumn::make('pass_count')->label('Passes')->numeric()->sortable()->alignCenter()->badge()->color('success'),
                Columns::dateTime('first_seen_at', 'First seen')->toggleable(isToggledHiddenByDefault: true),
                Columns::dateTime('last_seen_at', 'Last seen'),
            ])
            ->filters([
                SelectFilter::make('vehicle_category_id')->label('Vehicle category')->relationship('category', 'name_en')->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicles::route('/'),
        ];
    }
}
