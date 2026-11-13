<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Support\Toll;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /** Users with nothing to see on the dashboard land straight on the collection screen. */
    public function mount(): void
    {
        if (! $this->showsAnyWidget() && TollCollection::canAccess()) {
            $this->redirect(TollCollection::getUrl(), navigate: true);
        }
    }

    private function showsAnyWidget(): bool
    {
        return Toll::userCan(Permission::ViewCollectionSummary) || Toll::userCan(Permission::ViewDashboardStats);
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
