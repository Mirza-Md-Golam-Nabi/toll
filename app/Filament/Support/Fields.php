<?php

namespace App\Filament\Support;

use App\Models\Concerns\HasSortOrder;
use App\Rules\BangladeshMobileNumber;
use App\Services\ApprovalService;
use App\Services\RateService;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Settings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;

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

    /** 11-digit Bangladeshi mobile number; validated in its normalised form (+880 / Bangla digits accepted). */
    public static function mobileNumber(string $name = 'phone'): TextInput
    {
        return TextInput::make($name)
            ->label('Mobile number')
            ->tel()
            ->placeholder('01XXXXXXXXX')
            ->required()
            ->mutateStateForValidationUsing(fn (?string $state): ?string => PhoneNumber::normalize($state))
            ->rule(new BangladeshMobileNumber);
    }

    /** 4-5 digit login PIN, kept (hashed) in the `password` column. */
    public static function loginPin(string $label = 'PIN'): TextInput
    {
        return TextInput::make('password')
            ->label($label)
            ->validationAttribute(__($label))
            ->inputMode('numeric')
            ->minLength(4)
            ->maxLength(5)
            ->rule('digits_between:4,5');
    }

    /**
     * Position in a gap-free sequence; defaults to the next free position on create.
     *
     * @param  class-string<Model>  $model  A model using {@see HasSortOrder}.
     */
    public static function sortOrder(string $model): TextInput
    {
        return TextInput::make('sort_order')
            ->label('Sort order')
            ->integer()
            ->required()
            ->minValue(1)
            ->default(fn (): int => $model::nextSortOrder())
            ->helperText(__('Items at this position and after it move down by one.'));
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
