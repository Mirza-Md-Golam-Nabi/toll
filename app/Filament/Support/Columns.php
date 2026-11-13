<?php

namespace App\Filament\Support;

use App\Models\VehicleCategory;
use App\Support\Money;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

/**
 * Reusable table columns so every list renders money, dates and flags the same way.
 */
final class Columns
{
    public static function money(string $name = 'amount', string $label = 'Amount', bool $summarize = false): TextColumn
    {
        $column = TextColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn ($state): string => Money::format($state))
            ->alignEnd()
            ->weight('semibold')
            ->sortable();

        return $summarize
            ? $column->summarize(Sum::make()->label(__('Total'))->formatStateUsing(fn ($state): string => Money::format($state)))
            : $column;
    }

    public static function dateTime(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->dateTime('d M Y, h:i A')
            ->sortable();
    }

    /** Enum-cast attribute shown as a coloured badge (label & colour come from the enum). */
    public static function badge(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)->label($label)->badge();
    }

    public static function active(string $name = 'is_active', string $label = 'Active'): IconColumn
    {
        return IconColumn::make($name)->label($label)->boolean()->alignCenter();
    }

    /** Vehicle category with its own colour, via an eager-loaded relation. */
    public static function category(string $relation = 'category'): TextColumn
    {
        return TextColumn::make("{$relation}.name_bn")
            ->label('Vehicle category')
            ->formatStateUsing(fn ($record): ?string => $record->{$relation}?->display_name)
            ->placeholder('—')
            ->badge()
            ->color(fn ($record): array|string => $record->{$relation} ? Color::hex($record->{$relation}->color) : 'gray');
    }

    /** Filter by vehicle category (localised names, collection-screen order). */
    public static function categoryFilter(string $name = 'vehicle_category_id'): SelectFilter
    {
        return SelectFilter::make($name)->label('Vehicle category')->options(fn (): array => VehicleCategory::options());
    }

    /** @return array<int, TextColumn> */
    public static function timestamps(): array
    {
        return [
            self::dateTime('created_at', 'Created at')->toggleable(isToggledHiddenByDefault: true),
            self::dateTime('updated_at', 'Updated at')->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
