<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->boolean('portal_enabled')->default(false)->after('password');
            $table->string('invitation_token')->nullable()->after('portal_enabled');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
            $table->timestamp('password_set_at')->nullable()->after('invited_at');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'password', 'portal_enabled', 'invitation_token',
                'invited_at', 'password_set_at', 'remember_token',
            ]);
        });
    }
};
