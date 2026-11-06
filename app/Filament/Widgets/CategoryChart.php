<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\VisibleToManagers;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Filament\Widgets\ChartWidget;

class CategoryChart extends ChartWidget
{
    use VisibleToManagers;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '300px';

    public ?string $filter = '1';

    public function getHeading(): string
    {
        return __('Vehicles by category');
    }

    protected function getFilters(): ?array
    {
        return [
            '1' => __('Today'),
            '7' => __('Last 7 days'),
            '30' => __('Last 30 days'),
        ];
    }

    protected function getData(): array
    {
        $categories = app(ReportService::class)
            ->cached('byCategory', ReportFilters::lastDays((int) $this->filter))
            ->where('vehicles', '>', 0)
            ->values();

        return [
            'datasets' => [
                [
                    'data' => $categories->pluck('vehicles')->all(),
                    'backgroundColor' => $categories->pluck('color')->all(),
                    'borderWidth' => 2,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => $categories->pluck('label')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '62%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
