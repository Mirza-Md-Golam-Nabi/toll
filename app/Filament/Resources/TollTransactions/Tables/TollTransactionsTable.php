<?php

namespace App\Filament\Resources\TollTransactions\Tables;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Filament\Actions\TicketActions;
use App\Filament\Support\Columns;
use App\Support\Toll;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TollTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'category:id,code,name_bn,name_en,color,icon',
                'operator:id,name',
                'booth:id,code',
            ]))
            ->defaultSort('issued_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('ticket_no')->label('Ticket')->searchable()->fontFamily('mono')->weight('semibold')->copyable(),
                Columns::dateTime('issued_at', 'Issued at'),
                Columns::category(),
                TextColumn::make('plate_number')->label('Plate number')->searchable()->fontFamily('mono')->placeholder('—'),
                Columns::money('amount', 'Amount', summarize: true),
                Columns::badge('status', 'Status'),
                TextColumn::make('operator.name')->label('Operator')->toggleable(),
                TextColumn::make('booth.code')->label('Booth')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('print_count')->label('Prints')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('issued_at')
                    ->schema([
                        DatePicker::make('from')->label('From')->default(today()),
                        DatePicker::make('until')->label('Until')->default(today()),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('issued_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('issued_at', '<', now()->parse($date)->addDay())))
                    ->columnSpan(2),
                SelectFilter::make('status')->label('Status')->options(TransactionStatus::class),
                SelectFilter::make('vehicle_category_id')->label('Vehicle category')->relationship('category', 'name_en')->preload(),
                SelectFilter::make('user_id')
                    ->label('Operator')
                    ->relationship('operator', 'name')
                    ->preload()
                    ->visible(fn (): bool => Toll::userIsAtLeast(UserRole::PlazaManager)),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(5)
            ->recordActions([
                ViewAction::make(),
                TicketActions::group(),
            ]);
    }
}
