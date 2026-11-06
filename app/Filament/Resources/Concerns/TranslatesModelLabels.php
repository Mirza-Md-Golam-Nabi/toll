<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Str;

/**
 * Derives translated singular/plural labels from the model name for every resource
 * (e.g. "Vehicle Category" / "Vehicle Categories" looked up in lang/*.json).
 */
trait TranslatesModelLabels
{
    public static function getModelLabel(): string
    {
        return __(Str::headline(class_basename(static::getModel())));
    }

    public static function getPluralModelLabel(): string
    {
        return __(Str::plural(Str::headline(class_basename(static::getModel()))));
    }
}
