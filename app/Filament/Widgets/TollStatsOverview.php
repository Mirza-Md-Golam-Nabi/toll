<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\VisibleToManagers;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TollStatsOverview extends StatsOverviewWidget
{
    use VisibleToManagers;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $reports = app(ReportService::class);

        $today = $reports->cached('totals', ReportFilters::today());
        $yesterday = $reports->cached('totals', ReportFilters::fromArray(['from' => today()->subDay(), 'until' => today()->subDay()]));
        $month = $reports->cached('totals', ReportFilters::fromArray(['from' => today()->startOfMonth()]));
        $trend = $reports->cached('byDay', ReportFilters::lastDays(7));

        $change = $yesterday['revenue'] > 0
            ? round(($today['revenue'] - $yesterday['revenue']) / $yesterday['revenue'] * 100, 1)
            : null;

        return [
            Stat::make(__("Today's revenue"), Money::format($today['revenue']))
                ->description($change === null ? __('No collection yesterday') : __(':change% vs yesterday', ['change' => ($change > 0 ? '+' : '').$change]))
                ->descriptionIcon(($change ?? 0) >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color(($change ?? 0) >= 0 ? 'success' : 'danger')
                ->chart($trend->pluck('revenue')->all()),
            Stat::make(__('Vehicles today'), number_format($today['vehicles']))
                ->description(__('Yesterday: :count', ['count' => number_format($yesterday['vehicles'])]))
                ->descriptionIcon(Heroicon::Truck)
                ->color('info')
                ->chart($trend->pluck('vehicles')->all()),
            Stat::make(__('This month'), Money::format($month['revenue']))
                ->description(__(':count vehicles', ['count' => number_format($month['vehicles'])]))
                ->descriptionIcon(Heroicon::CalendarDays)
                ->color('primary'),
            Stat::make(__('Exempt / voided today'), $today['exempt_count'].' / '.$today['voided_count'])
                ->description(__('Manual overrides are in the audit trail'))
                ->descriptionIcon(Heroicon::ShieldExclamation)
                ->color('warning'),
        ];
    }
}
