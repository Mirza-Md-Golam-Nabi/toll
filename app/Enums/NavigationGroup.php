<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sidebar groups, in display order. Labels are translated at render time.
 */
enum NavigationGroup implements HasLabel
{
    case Operations;
    case Reports;
    case Configuration;

    public function getLabel(): string
    {
        return __('enums.navigation_group.'.$this->name);
    }
}
