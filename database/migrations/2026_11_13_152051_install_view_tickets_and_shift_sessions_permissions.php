<?php

use App\Enums\Permission;
use App\Services\RolePermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives the "Toll Tickets" and "Shift Sessions" menus their own permissions so the
 * Super Admin can choose which roles see them.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionService::class)->install();
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', [Permission::ViewTollTransactions->value, Permission::ViewShiftSessions->value])
            ->delete();
    }
};
