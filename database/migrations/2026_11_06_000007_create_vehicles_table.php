<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number', 40)->unique();
            $table->foreignId('vehicle_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('owner_phone', 20)->nullable();
            $table->unsignedInteger('pass_count')->default(0);
            $table->dateTime('first_seen_at')->nullable();
            $table->dateTime('last_seen_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
