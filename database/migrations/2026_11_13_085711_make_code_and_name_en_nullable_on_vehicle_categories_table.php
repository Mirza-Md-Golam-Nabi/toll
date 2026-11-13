<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Only the Bangla name is mandatory for a vehicle category; the code (still unique when given)
 * and the English name are optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->change();
            $table->string('name_en')->nullable()->change();
        });

        DB::table('vehicle_categories')->where('code', '')->update(['code' => null]);
        DB::table('vehicle_categories')->where('name_en', '')->update(['name_en' => null]);
    }

    public function down(): void
    {
        DB::table('vehicle_categories')->whereNull('code')->update(['code' => DB::raw("CONCAT('CAT', id)")]);
        DB::table('vehicle_categories')->whereNull('name_en')->update(['name_en' => DB::raw('name_bn')]);

        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->string('code', 20)->nullable(false)->change();
            $table->string('name_en')->nullable(false)->change();
        });
    }
};
