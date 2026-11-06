<?php

namespace App\Filament\Actions;

use App\Filament\Support\Feedback;
use App\Filament\Support\Fields;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\User;
use App\Services\ShiftService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Open / close shift actions shared by the collection screen and the shift sessions list.
 */
final class ShiftActions
{
    public static function open(): Action
    {
        return Action::make('openShift')
            ->label(__('Open shift'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->size('lg')
            ->modalWidth('md')
            ->schema([
                Select::make('shift_id')
                    ->label('Shift')
                    ->options(fn (): array => Shift::query()->active()->orderBy('sort_order')->get()
                        ->mapWithKeys(fn (Shift $shift) => [$shift->id => "{$shift->name} ({$shift->time_range})"])
                        ->all())
                    ->default(fn () => app(ShiftService::class)->detectShift()?->id)
                    ->required()
                    ->native(false),
                Fields::money('opening_cash', 'Opening cash (change in drawer)')->default(0),
            ])
            ->action(fn (array $data, Action $action) => Feedback::attempt(
                fn () => app(ShiftService::class)->open(
                    auth()->user(),
                    Shift::query()->findOrFail($data['shift_id']),
                    $data['opening_cash'] ?? 0,
                ),
                __('Shift opened. You can start collecting.'),
                $action,
            ));
    }

    public static function close(): Action
    {
        return Action::make('closeShift')
            ->label(__('Close shift'))
            ->icon(Heroicon::OutlinedStopCircle)
            ->color('danger')
            ->authorize('close')
            ->modalWidth('md')
            ->modalDescription(function (ShiftSession $record): string {
                $summary = app(ShiftService::class)->summary($record);
                $expected = (float) $record->opening_cash + $summary['revenue'];

                return __('Vehicles: :vehicles · Expected cash in drawer: :amount', [
                    'vehicles' => $summary['vehicles'],
                    'amount' => Money::format($expected),
                ]);
            })
            ->schema([
                Fields::money('declared_cash', 'Cash counted in drawer')->required(),
                Textarea::make('notes')->label('Notes')->rows(2),
            ])
            ->action(fn (array $data, ShiftSession $record, Action $action) => Feedback::attempt(
                function () use ($data, $record): ShiftSession {
                    /** @var User $user */
                    $user = auth()->user();

                    return app(ShiftService::class)->close($record, $data['declared_cash'], $data['notes'] ?? null, $user);
                },
                __('Shift closed.'),
                $action,
            ));
    }
}
