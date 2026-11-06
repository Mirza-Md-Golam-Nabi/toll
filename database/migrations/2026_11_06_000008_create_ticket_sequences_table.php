<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booth_id')->constrained()->cascadeOnDelete();
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['booth_id', 'sequence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sequences');
    }
};
