<?php

namespace App\Filament\Actions;

use App\Filament\Support\Feedback;
use App\Filament\Support\Fields;
use App\Filament\Support\ReceiptDispatcher;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\ApprovalService;
use App\Services\TollTransactionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Manual override actions for a ticket. Used by the transactions table and the ticket view page.
 */
final class TicketActions
{
    public static function group(): ActionGroup
    {
        return ActionGroup::make(self::all())
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->tooltip(__('Override'));
    }

    /** @return array<int, Action> */
    public static function all(): array
    {
        return [self::reprint(), self::correctCategory(), self::void()];
    }

    public static function reprint(): Action
    {
        return Action::make('reprint')
            ->label(__('Reprint'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->authorize('override')
            ->requiresConfirmation()
            ->modalDescription(__('The receipt will be marked DUPLICATE and logged.'))
            ->action(fn (TollTransaction $record, Component $livewire) => Feedback::attempt(function () use ($record, $livewire): void {
                app(TollTransactionService::class)->registerReprint($record, auth()->user());
                ReceiptDispatcher::send($livewire, $record);
            }, __('Receipt sent to printer.')));
    }

    public static function correctCategory(): Action
    {
        return Action::make('correctCategory')
            ->label(__('Correct category'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('warning')
            ->authorize('override')
            ->schema([
                Fields::category(),
                Fields::reason(),
                ...Fields::approval(),
            ])
            ->action(fn (array $data, TollTransaction $record, Action $action) => Feedback::attempt(
                fn () => app(TollTransactionService::class)->correctCategory(
                    $record,
                    VehicleCategory::query()->findOrFail($data['vehicle_category_id']),
                    $data['reason'],
                    auth()->user(),
                    self::approver($data),
                ),
                __('Category corrected.'),
                $action,
            ));
    }

    public static function void(): Action
    {
        return Action::make('void')
            ->label(__('Void ticket'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('override')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalDescription(__('A voided ticket is excluded from collection. This cannot be undone.'))
            ->schema([
                Fields::reason(),
                ...Fields::approval(),
            ])
            ->action(fn (array $data, TollTransaction $record, Action $action) => Feedback::attempt(
                fn () => app(TollTransactionService::class)->void($record, $data['reason'], auth()->user(), self::approver($data)),
                __('Ticket voided.'),
                $action,
            ));
    }

    private static function approver(array $data): User
    {
        return app(ApprovalService::class)->resolve(
            auth()->user(),
            isset($data['approver_id']) ? (int) $data['approver_id'] : null,
            $data['approval_pin'] ?? null,
        );
    }
}
