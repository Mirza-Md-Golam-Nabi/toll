<?php

namespace App\Filament\Resources\TollTransactions\Pages;

use App\Filament\Actions\TicketActions;
use App\Filament\Resources\TollTransactions\TollTransactionResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewTollTransaction extends ViewRecord
{
    protected static string $resource = TollTransactionResource::class;

    /** Eager-load everything the infolist shows in one go. */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'category:id,code,name_bn,name_en,color',
            'operator:id,name',
            'booth:id,code,name',
            'voider:id,name',
            'approver:id,name',
            'auditLogs' => fn ($query) => $query->with(['user:id,name', 'approver:id,name'])->latest('created_at'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return TicketActions::all();
    }
}
