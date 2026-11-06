<?php

namespace App\Filament\Support;

use App\Services\ApprovalService;
use App\Services\RateService;
use App\Support\Money;
use App\Support\Settings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Reusable form fields shared by resources, page actions and override modals.
 */
final class Fields
{
    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->prefix(fn (): string => Settings::get('currency', '৳'));
    }

    public static function active(): Toggle
    {
        return Toggle::make('is_active')->label('Active')->default(true);
    }

    public static function sortOrder(): TextInput
    {
        return TextInput::make('sort_order')->label('Sort order')->numeric()->default(0)->minValue(0);
    }

    public static function reason(string $name = 'reason', string $label = 'Reason'): Textarea
    {
        return Textarea::make($name)->label($label)->required()->rows(2)->maxLength(255);
    }

    /** Active categories with their current rate, from the cached rate list (no extra queries). */
    public static function category(string $name = 'vehicle_category_id'): Select
    {
        return Select::make($name)
            ->label('Vehicle category')
            ->options(fn (): array => app(RateService::class)->activeCategories()
                ->mapWithKeys(fn ($category) => [$category->id => $category->display_name.' — '.Money::format($category->current_amount)])
                ->all())
            ->required()
            ->native(false);
    }

    /**
     * Manager approval inputs; only shown (and required) when the acting user is an operator.
     *
     * @return array<int, Select|TextInput>
     */
    public static function approval(): array
    {
        $needsPin = fn (): bool => app(ApprovalService::class)->requiresPin(auth()->user());

        return [
            Select::make('approver_id')
                ->label('Approving manager')
                ->options(fn (): array => app(ApprovalService::class)->approverOptions())
                ->visible($needsPin)
                ->required($needsPin)
                ->native(false),
            TextInput::make('approval_pin')
                ->label('Manager PIN')
                ->password()
                ->visible($needsPin)
                ->required($needsPin),
        ];
    }
}
