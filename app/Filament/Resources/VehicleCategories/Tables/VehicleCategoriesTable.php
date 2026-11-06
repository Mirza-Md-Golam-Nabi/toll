<?php

namespace App\Filament\Resources\VehicleCategories\Tables;

use App\Filament\Support\Columns;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Current rate comes from a correlated sub-select: one query for the whole page.
            ->modifyQueryUsing(fn (Builder $query) => $query->select('vehicle_categories.*')->withCurrentAmount())
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ColorColumn::make('color')->label('Color'),
                TextColumn::make('icon')->label('Icon')->size('lg')->placeholder('—'),
                TextColumn::make('code')->label('Code')->badge()->color('gray')->searchable(),
                TextColumn::make('name_bn')->label('Name (Bangla)')->weight('semibold')->searchable()
                    ->description(fn ($record) => $record->name_en),
                Columns::money('current_amount', 'Current rate')->sortable(false),
                TextColumn::make('shortcut_key')->label('Shortcut key')->badge()->color('info')->placeholder('—'),
                IconColumn::make('is_exempt')->label('Exempt')->boolean()->alignCenter(),
                Columns::active(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('is_exempt')->label('Exempt'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
