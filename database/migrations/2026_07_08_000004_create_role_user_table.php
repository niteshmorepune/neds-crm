<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additional roles a user holds beyond their primary `users.role`.
        // Primary role still drives sidebar caching, the dashboard panel, and
        // 2FA enforcement; additional roles only expand permission checks and
        // role-targeted notifications/dropdowns (see User::hasRole()).
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', UserRole::values());
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
