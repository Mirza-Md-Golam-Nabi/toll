<?php

namespace App\Filament\Pages;

use App\Enums\AppMode;
use App\Enums\NavigationGroup;
use App\Enums\Permission;
use App\Enums\PlateSource;
use App\Filament\Actions\ShiftActions;
use App\Filament\Support\Feedback;
use App\Filament\Support\Fields;
use App\Filament\Support\ReceiptDispatcher;
use App\Models\ShiftSession;
use App\Models\TollTransaction;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\RateService;
use App\Services\ShiftService;
use App\Services\TollTransactionService;
use App\Services\TransactionAggregates;
use App\Support\Money;
use App\Support\PlateNumber;
use App\Support\Toll;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * The booth operator's screen: one key press (or click) issues and prints a ticket.
 */
class TollCollection extends Page
{
    protected string $view = 'filament.pages.toll-collection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'collection';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?string $plateNumber = null;

    public static function canAccess(): bool
    {
        return AppMode::isBooth() && Toll::userCan(Permission::CollectToll);
    }

    public static function getNavigationLabel(): string
    {
        return __('Toll Collection');
    }

    public function getTitle(): string
    {
        return __('Toll Collection');
    }

    #[Computed]
    public function session(): ?ShiftSession
    {
        return app(ShiftService::class)->currentSession(auth()->user());
    }

    /** @return Collection<int, VehicleCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return app(RateService::class)->activeCategories();
    }

    /** Whether the shift summary cards (shift, vehicles, collected cash, booth) are shown. */
    #[Computed]
    public function canViewShiftSummary(): bool
    {
        return Toll::userCan(Permission::ViewShiftSummary);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->session
            ? app(ShiftService::class)->summary($this->session)
            : TransactionAggregates::normalize(null);
    }

    /** @return Collection<int, TollTransaction> */
    #[Computed]
    public function recentTickets(): Collection
    {
        if (! $this->session) {
            return collect();
        }

        return TollTransaction::query()
            ->where('shift_session_id', $this->session->id)
            ->with('category:id,name_bn,name_en,color,icon')
            ->newestFirst()
            ->limit(8)
            ->get();
    }

    /** Prefix search on the unique plate index — only after 3 characters. */
    #[Computed]
    public function plateSuggestions(): Collection
    {
        $plate = PlateNumber::normalize($this->plateNumber);

        if ($plate === null || mb_strlen($plate) < 3) {
            return collect();
        }

        return Vehicle::query()
            ->where('plate_number', 'like', $plate.'%')
            ->where('plate_number', '!=', $plate)
            ->orderByDesc('last_seen_at')
            ->limit(5)
            ->pluck('plate_number');
    }

    /** @return array<string, int> shortcut key => category id */
    public function shortcuts(): array
    {
        return $this->categories
            ->filter(fn (VehicleCategory $category) => filled($category->shortcut_key))
            ->mapWithKeys(fn (VehicleCategory $category) => [$category->shortcut_key => $category->id])
            ->all();
    }

    public function issue(int $categoryId): void
    {
        $category = $this->categories->firstWhere('id', $categoryId);

        if (! $category) {
            return;
        }

        if ($category->is_exempt) {
            $this->mountAction('issueExempt', ['category' => $categoryId]);

            return;
        }

        $this->issueTicket($category);
    }

    public function choosePlate(string $plate): void
    {
        $this->plateNumber = $plate;
    }

    public function reprint(string $transactionId): void
    {
        $transaction = $this->recentTickets->firstWhere('id', $transactionId);

        if (! $transaction) {
            return;
        }

        Feedback::attempt(function () use ($transaction): void {
            app(TollTransactionService::class)->registerReprint($transaction, auth()->user());
            ReceiptDispatcher::send($this, $transaction);
        }, __('Receipt sent to printer.'));

        $this->refreshState();
    }

    public function issueExemptAction(): Action
    {
        return Action::make('issueExempt')
            ->modalHeading(__('Exempt vehicle'))
            ->modalWidth('md')
            ->modalSubmitActionLabel(__('Issue ticket'))
            ->schema([Fields::reason('exempt_reason', 'Exempt reason')])
            ->action(fn (array $data, array $arguments) => $this->issueTicket(
                $this->categories->firstWhere('id', $arguments['category']),
                $data['exempt_reason'],
            ));
    }

    protected function getHeaderActions(): array
    {
        return [
            ShiftActions::open()
                ->visible(fn (): bool => $this->session === null)
                ->after(fn () => $this->refreshState()),
            ShiftActions::close()
                ->record(fn (): ?ShiftSession => $this->session)
                ->visible(fn (): bool => $this->session !== null)
                ->after(fn () => $this->refreshState()),
        ];
    }

    private function issueTicket(VehicleCategory $category, ?string $exemptReason = null): void
    {
        $transaction = Feedback::attempt(fn () => app(TollTransactionService::class)->issue(
            auth()->user(),
            $category,
            $this->plateNumber,
            PlateSource::Manual,
            exemptReason: $exemptReason,
        ));

        if (! $transaction) {
            return;
        }

        ReceiptDispatcher::send($this, $transaction);

        Notification::make()
            ->title($transaction->ticket_no.' · '.Money::format($transaction->amount))
            ->body($category->display_name)
            ->success()
            ->duration(2500)
            ->send();

        $this->reset('plateNumber');
        $this->refreshState();
        $this->dispatch('toll-ticket-issued');
    }

    private function refreshState(): void
    {
        unset($this->session, $this->summary, $this->recentTickets, $this->plateSuggestions);
    }
}
