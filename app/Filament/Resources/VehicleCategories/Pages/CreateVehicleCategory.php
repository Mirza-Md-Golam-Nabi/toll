<?php

namespace App\Filament\Resources\VehicleCategories\Pages;

use App\Filament\Resources\VehicleCategories\VehicleCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleCategory extends CreateRecord
{
    protected static string $resource = VehicleCategoryResource::class;

    /** Every category starts with a rate effective immediately. */
    protected function afterCreate(): void
    {
        $this->record->rates()->create([
            'amount' => $this->data['initial_rate'] ?? 0,
            'effective_from' => now(),
            'note' => __('Initial rate'),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
