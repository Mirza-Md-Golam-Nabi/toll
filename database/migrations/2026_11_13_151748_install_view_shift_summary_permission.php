<?php

use App\Enums\Permission;
use App\Services\RolePermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the shift summary cards at the top of the toll collection screen into their own
 * permission so the Super Admin can choose which roles see them.
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
            ->where('name', Permission::ViewShiftSummary->value)
            ->delete();
    }
};
