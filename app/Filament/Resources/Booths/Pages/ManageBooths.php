<?php

namespace App\Filament\Resources\Booths\Pages;

use App\Filament\Resources\Booths\BoothResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBooths extends ManageRecords
{
    protected static string $resource = BoothResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
