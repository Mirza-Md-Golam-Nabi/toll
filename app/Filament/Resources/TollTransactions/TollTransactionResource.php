<?php

namespace App\Filament\Resources\TollTransactions;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Concerns\ScopesRecordsToOperator;
use App\Filament\Resources\Concerns\TranslatesModelLabels;
use App\Filament\Resources\TollTransactions\Pages\ListTollTransactions;
use App\Filament\Resources\TollTransactions\Pages\ViewTollTransaction;
use App\Filament\Resources\TollTransactions\Schemas\TollTransactionInfolist;
use App\Filament\Resources\TollTransactions\Tables\TollTransactionsTable;
use App\Models\TollTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TollTransactionResource extends Resource
{
    use ScopesRecordsToOperator, TranslatesModelLabels;

    protected static ?string $model = TollTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'ticket_no';

    public static function infolist(Schema $schema): Schema
    {
        return TollTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TollTransactionsTable::configure($table);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ticket_no', 'plate_number'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTollTransactions::route('/'),
            'view' => ViewTollTransaction::route('/{record}'),
        ];
    }
}
