<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 30)->default(UserRole::BoothOperator->value)->index()->after('phone');
            $table->foreignId('booth_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->string('approval_pin')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('approval_pin');
            $table->string('locale', 5)->default('bn')->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booth_id');
            $table->dropColumn(['phone', 'role', 'approval_pin', 'is_active', 'locale', 'last_login_at']);
        });
    }
};
