<?php

namespace App\Filament\Resources\VehicleCategories\RelationManagers;

use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Models\TollRate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;

/**
 * Rate history. A new rate never overwrites the old one — it starts at `effective_from`,
 * so past tickets and reports keep their original amounts.
 */
class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Toll Rates');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fields::money('amount', 'Amount')->required(),
                DateTimePicker::make('effective_from')->label('Effective from')->required()->default(now())->seconds(false),
                TextInput::make('note')->label('Note')->maxLength(255)->columnSpanFull(),
            ]);
    }

    /** Id of the rate in force now — computed once per request, not per row. */
    #[Computed]
    public function currentRateId(): ?int
    {
        return $this->getOwnerRecord()->currentRate()->value('toll_rates.id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator:id,name'))
            ->defaultSort('effective_from', 'desc')
            ->columns([
                Columns::money('amount', 'Amount'),
                Columns::dateTime('effective_from', 'Effective from'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (TollRate $record): string => match (true) {
                        $record->effective_from->isFuture() => 'Scheduled',
                        $record->id === $this->currentRateId => 'Current',
                        default => 'History',
                    })
                    ->formatStateUsing(fn (string $state): string => __($state))
                    ->color(fn (string $state): string => match ($state) {
                        'Scheduled' => 'warning',
                        'Current' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('note')->label('Note')->placeholder('—'),
                TextColumn::make('creator.name')->label('Created by')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label(__('New rate')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
