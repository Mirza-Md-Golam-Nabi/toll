<?php

use App\Enums\PlateSource;
use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toll_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_no', 30)->unique();
            $table->foreignId('booth_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('shift_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 10)->default(TransactionStatus::Paid->value);
            $table->string('exempt_reason')->nullable();

            // Number plate (Phase 1: manual / skipped, Phase 2: ANPR).
            $table->string('plate_number', 40)->nullable()->index();
            $table->string('plate_source', 10)->default(PlateSource::None->value);
            $table->string('plate_image')->nullable();
            $table->decimal('anpr_confidence', 5, 2)->nullable();
            $table->string('direction', 10)->nullable();

            $table->unsignedTinyInteger('print_count')->default(0);
            $table->dateTime('issued_at');

            // Manual override (void) tracking.
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            // Report / dashboard access paths.
            $table->index(['issued_at', 'status']);
            $table->index(['booth_id', 'issued_at']);
            $table->index(['shift_session_id', 'status']);
            $table->index(['vehicle_category_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toll_transactions');
    }
};
