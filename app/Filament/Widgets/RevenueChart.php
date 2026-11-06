<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\VisibleToManagers;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    use VisibleToManagers;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected ?string $maxHeight = '300px';

    public ?string $filter = '14';

    public function getHeading(): string
    {
        return __('Daily revenue');
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => __('Last 7 days'),
            '14' => __('Last 14 days'),
            '30' => __('Last 30 days'),
        ];
    }

    protected function getData(): array
    {
        $days = app(ReportService::class)->cached('byDay', ReportFilters::lastDays((int) $this->filter));

        return [
            'datasets' => [
                [
                    'label' => __('Revenue'),
                    'data' => $days->pluck('revenue')->all(),
                    'borderColor' => '#0d9488',
                    'backgroundColor' => 'rgba(20, 184, 166, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                ],
                [
                    'label' => __('Vehicles'),
                    'data' => $days->pluck('vehicles')->all(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [6, 4],
                    'tension' => 0.35,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $days->map(fn (array $day) => Carbon::parse($day['label'])->format('d M'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true],
                'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
