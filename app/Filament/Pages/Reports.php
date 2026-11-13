<?php

namespace App\Filament\Pages;

use App\Enums\AppMode;
use App\Enums\NavigationGroup;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Booth;
use App\Models\Shift;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use App\Support\Toll;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Daily, shift-wise, category-wise and operator-wise collection for any date range.
 */
class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed> */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return Toll::userCan(Permission::ViewReports);
    }

    public static function getNavigationLabel(): string
    {
        return __('Collection Reports');
    }

    public function getTitle(): string
    {
        return __('Collection Reports');
    }

    public function mount(): void
    {
        $this->filtersForm->fill([
            'from' => today()->startOfMonth()->toDateString(),
            'until' => today()->toDateString(),
        ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(['default' => 1, 'sm' => 2, 'xl' => 6])
            ->components([
                DatePicker::make('from')->label('From')->required()->live()->native(false)->maxDate(fn () => today()),
                DatePicker::make('until')->label('Until')->required()->live()->native(false)->maxDate(fn () => today()),
                Select::make('booth_id')->label('Booth')->live()
                    ->options(fn () => Booth::query()->orderBy('code')->pluck('name', 'id'))
                    ->visible(AppMode::isCentral()),
                Select::make('shift_id')->label('Shift')->live()
                    ->options(fn () => Shift::query()->orderBy('sort_order')->pluck('name', 'id')),
                Select::make('user_id')->label('Operator')->live()->searchable()
                    ->options(fn () => User::query()->where('role', UserRole::BoothOperator)->orderBy('name')->pluck('name', 'id')),
                Select::make('vehicle_category_id')->label('Vehicle category')->live()
                    ->options(fn (): array => VehicleCategory::options()),
            ]);
    }

    #[Computed]
    public function reportFilters(): ReportFilters
    {
        return ReportFilters::fromArray($this->filters ?? []);
    }

    #[Computed]
    public function totals(): array
    {
        return app(ReportService::class)->totals($this->reportFilters);
    }

    #[Computed]
    public function daily(): Collection
    {
        return app(ReportService::class)->byDay($this->reportFilters);
    }

    #[Computed]
    public function categories(): Collection
    {
        return app(ReportService::class)->byCategory($this->reportFilters);
    }

    #[Computed]
    public function shifts(): Collection
    {
        return app(ReportService::class)->byShiftSession($this->reportFilters);
    }

    #[Computed]
    public function operators(): Collection
    {
        return app(ReportService::class)->byOperator($this->reportFilters);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action(fn (): StreamedResponse => app(ReportService::class)->exportCsv($this->reportFilters)),
            Action::make('print')
                ->label(__('Print'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->alpineClickHandler('window.print()'),
        ];
    }
}
