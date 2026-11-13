<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shifts and tickets created on a phone remember which phone created them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['shift_sessions', 'toll_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('mobile_device_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['shift_sessions', 'toll_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('mobile_device_id');
            });
        }
    }
};
