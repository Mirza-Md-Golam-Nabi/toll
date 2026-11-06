<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TollTransactions\TollTransactionResource;
use App\Filament\Support\Columns;
use App\Filament\Widgets\Concerns\VisibleToManagers;
use App\Models\TollTransaction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestTickets extends TableWidget
{
    use VisibleToManagers;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Latest tickets'))
            ->query(fn () => TollTransaction::query()
                ->with(['category:id,name_bn,name_en,color', 'operator:id,name'])
                ->newestFirst()
                ->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('ticket_no')->label('Ticket')->fontFamily('mono')->weight('semibold'),
                Columns::dateTime('issued_at', 'Issued at')->sortable(false)->since(),
                Columns::category(),
                TextColumn::make('plate_number')->label('Plate number')->fontFamily('mono')->placeholder('—'),
                Columns::money('amount', 'Amount')->sortable(false),
                Columns::badge('status', 'Status'),
                TextColumn::make('operator.name')->label('Operator'),
            ])
            ->recordUrl(fn (TollTransaction $record): string => TollTransactionResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('all')
                    ->label(__('View all'))
                    ->url(TollTransactionResource::getUrl())
                    ->link(),
            ]);
    }
}
