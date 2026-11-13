<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

/**
 * Every permission the Super Admin can grant to a role. Names are stored in spatie's
 * `permissions` table; the Super Admin always holds all of them.
 */
enum Permission: string implements HasLabel
{
    use EnumHelpers;

    case CollectToll = 'toll.collect';
    case ViewShiftSummary = 'toll.view_shift_summary';
    case ViewTollTransactions = 'records.view_tickets';
    case ViewShiftSessions = 'records.view_shift_sessions';
    case ViewAllRecords = 'records.view_all';
    case ApproveOverrides = 'overrides.approve';
    case ViewCollectionSummary = 'dashboard.view_collection_summary';
    case ViewDashboardStats = 'dashboard.view_stats';
    case ViewReports = 'reports.view';
    case ViewAuditLogs = 'audit_logs.view';
    case ViewVehicles = 'vehicles.view';
    case UpdateVehicles = 'vehicles.update';
    case ViewVehicleCategories = 'vehicle_categories.view';
    case ManageVehicleCategories = 'vehicle_categories.manage';
    case ManageTollRates = 'toll_rates.manage';
    case ViewShifts = 'shifts.view';
    case ManageShifts = 'shifts.manage';
    case ViewBooths = 'booths.view';
    case ManageBooths = 'booths.manage';
    case ViewMobileDevices = 'mobile_devices.view';
    case ManageMobileDevices = 'mobile_devices.manage';
    case ViewUsers = 'users.view';
    case ManageUsers = 'users.manage';
    case ManageSettings = 'settings.manage';

    /** Keyed by case name because the values contain dots. */
    public function getLabel(): string
    {
        return __('enums.permission.'.$this->name);
    }

    /** A longer explanation of what this permission unlocks, shown in the info tooltip on the role screen. */
    public function description(): string
    {
        return __('enums.permission_description.'.$this->name);
    }

    /** The section this permission is listed under on the role screen (e.g. `booths`). */
    public function group(): string
    {
        return Str::before($this->value, '.');
    }

    public static function groupLabel(string $group): string
    {
        return __('enums.permission_group.'.$group);
    }

    /** @return array<string, list<self>> group => permissions, in declaration order. */
    public static function grouped(): array
    {
        return collect(self::cases())->groupBy(fn (self $permission): string => $permission->group())->map->all()->all();
    }

    /**
     * Roles that receive this permission on a fresh install (the Super Admin is implicit).
     *
     * @return list<UserRole>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::CollectToll, self::ViewShiftSummary, self::ViewTollTransactions, self::ViewShiftSessions => [UserRole::PlazaManager, UserRole::BoothOperator],
            self::ManageBooths, self::ViewUsers, self::ManageUsers, self::ManageSettings => [],
            default => [UserRole::PlazaManager],
        };
    }
}
