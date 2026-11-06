<?php

namespace App\Filament\Pages;

use App\Enums\AppMode;
use App\Support\Toll;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /** Operators land straight on the collection screen. */
    public function mount(): void
    {
        if (Toll::user()?->isOperator() && AppMode::isBooth()) {
            $this->redirect(TollCollection::getUrl(), navigate: true);
        }
    }

    public function getTitle(): string
    {
        return __('Dashboard');
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
