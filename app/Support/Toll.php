<?php

namespace App\Support;

use App\Enums\AppMode;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Cross-cutting permission rules used by policies, pages and actions.
 */
class Toll
{
    public static function syncEnabled(): bool
    {
        return filled(config('toll.sync.central_url')) && filled(config('toll.sync.token'));
    }

    /**
     * Master data (booths, users, categories, rates, shifts, settings) is owned by the central
     * server. A booth may only edit it when it is running stand-alone (no central configured).
     */
    public static function canEditMasterData(): bool
    {
        return AppMode::isCentral() || ! self::syncEnabled();
    }

    public static function user(): ?User
    {
        return auth()->user();
    }

    public static function userIsAtLeast(UserRole $role): bool
    {
        return (bool) self::user()?->isAtLeast($role);
    }
}
