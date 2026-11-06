<?php

use App\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('booth_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default(ShiftStatus::Open->value);
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('declared_cash', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->nullable();
            $table->unsignedInteger('transaction_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['booth_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_sessions');
    }
};
