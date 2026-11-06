<?php

namespace App\Filament\Resources\TollTransactions\Pages;

use App\Filament\Resources\TollTransactions\TollTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListTollTransactions extends ListRecords
{
    protected static string $resource = TollTransactionResource::class;
}
