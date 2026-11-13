<?php

use App\Services\RolePermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates the three roles and every permission, granting the defaults that match how
 * access worked before roles became configurable.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionService::class)->install();
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.role_has_permissions'))->delete();
        DB::table(config('permission.table_names.permissions'))->delete();
        DB::table(config('permission.table_names.roles'))->delete();
    }
};
