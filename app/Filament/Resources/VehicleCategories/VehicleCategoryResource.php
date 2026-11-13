<?php

namespace App\Filament\Resources\VehicleCategories;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\VehicleCategories\Pages\CreateVehicleCategory;
use App\Filament\Resources\VehicleCategories\Pages\EditVehicleCategory;
use App\Filament\Resources\VehicleCategories\Pages\ListVehicleCategories;
use App\Filament\Resources\VehicleCategories\RelationManagers\RatesRelationManager;
use App\Filament\Resources\VehicleCategories\Schemas\VehicleCategoryForm;
use App\Filament\Resources\VehicleCategories\Tables\VehicleCategoriesTable;
use App\Models\VehicleCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VehicleCategoryResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = VehicleCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name_bn';

    public static function form(Schema $schema): Schema
    {
        return VehicleCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleCategories::route('/'),
            'create' => CreateVehicleCategory::route('/create'),
            'edit' => EditVehicleCategory::route('/{record}/edit'),
        ];
    }
}
