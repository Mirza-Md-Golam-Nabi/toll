<?php

use App\Enums\Permission;
use App\Services\RolePermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the dashboard's toll collection summary (the cards at the top) into its own
 * permission so the Super Admin can choose which roles see it.
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
            ->where('name', Permission::ViewCollectionSummary->value)
            ->delete();
    }
};
