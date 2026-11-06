<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Shared helpers for every backed enum in the app (labels, select options, values).
 */
trait EnumHelpers
{
    /** @return array<string, string> value => translated label, ready for Select / filters. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return __('enums.'.static::translationKey().'.'.$this->value);
    }

    protected static function translationKey(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
