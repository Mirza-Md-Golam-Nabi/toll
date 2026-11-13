<?php

namespace App\Filament\Resources\VehicleCategories\Schemas;

use App\Filament\Support\Fields;
use App\Models\VehicleCategory;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleCategoryForm
{
    /** Keys an operator can press on the collection screen. */
    public const SHORTCUT_KEYS = ['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'F12'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Category'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        TextInput::make('code')
                            ->label('Code')
                            ->maxLength(20)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->mutateStateForValidationUsing(fn (?string $state): ?string => VehicleCategory::normalizeCode($state))
                            ->placeholder('CAR'),
                        TextInput::make('icon')->label('Icon (emoji)')->maxLength(8)->placeholder('🚗'),
                        TextInput::make('name_bn')->label('Name (Bangla)')->required()->maxLength(255),
                        TextInput::make('name_en')->label('Name (English)')->maxLength(255),
                        Fields::money('initial_rate', 'Initial toll rate')
                            ->required()
                            ->visibleOn('create')
                            ->dehydrated(false)
                            ->helperText(__('Later rate changes are made from the Rates tab with an effective date.')),
                    ]),
                Section::make(__('Collection screen'))
                    ->columnSpan(1)
                    ->components([
                        Select::make('shortcut_key')
                            ->label('Shortcut key')
                            ->options(array_combine(self::SHORTCUT_KEYS, self::SHORTCUT_KEYS))
                            ->unique(ignoreRecord: true)
                            ->native(false),
                        ColorPicker::make('color')->label('Color')->required()->default('#0d9488'),
                        Fields::sortOrder(VehicleCategory::class),
                        Toggle::make('is_exempt')->label('Exempt (free pass)')->helperText(__('Ambulance, fire service, etc. A reason can optionally be noted on each ticket.')),
                        Fields::active(),
                    ]),
            ]);
    }
}
