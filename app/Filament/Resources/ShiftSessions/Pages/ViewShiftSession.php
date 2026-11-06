<?php

namespace App\Filament\Resources\ShiftSessions\Pages;

use App\Filament\Actions\ShiftActions;
use App\Filament\Resources\ShiftSessions\ShiftSessionResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewShiftSession extends ViewRecord
{
    protected static string $resource = ShiftSessionResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['shift:id,name', 'user:id,name', 'booth:id,name']);
    }

    protected function getHeaderActions(): array
    {
        return [ShiftActions::close()];
    }
}
