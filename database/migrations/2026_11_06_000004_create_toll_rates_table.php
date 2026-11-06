<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toll_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->dateTime('effective_from');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Rate lookup: latest effective_from <= now for a category.
            $table->index(['vehicle_category_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toll_rates');
    }
};
