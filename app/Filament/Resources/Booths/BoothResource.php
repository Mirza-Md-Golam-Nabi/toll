<?php

namespace App\Filament\Resources\Booths;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Booths\Pages\ManageBooths;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Models\Booth;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BoothResource extends Resource
{
    use TranslatesModelLabels;

    protected static ?string $model = Booth::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Code')->required()->maxLength(10)->unique(ignoreRecord: true)->placeholder('B01'),
                TextInput::make('name')->label('Name')->required()->maxLength(255),
                TextInput::make('location')->label('Location')->maxLength(255),
                TextInput::make('printer_name')->label('Printer name')->maxLength(255)->placeholder('POS58'),
                Fields::active(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('users'))
            ->columns([
                TextColumn::make('code')->label('Code')->badge()->color('primary')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->weight('semibold'),
                TextColumn::make('location')->label('Location')->placeholder('—'),
                TextColumn::make('users_count')->label('Users')->alignCenter(),
                Columns::active(),
                Columns::dateTime('last_synced_at', 'Last synced')->placeholder(__('Never')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBooths::route('/'),
        ];
    }
}
