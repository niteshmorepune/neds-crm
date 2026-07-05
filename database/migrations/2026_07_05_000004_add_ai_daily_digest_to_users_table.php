<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ai_daily_digest')->nullable()->after('device_user_id');
            $table->date('ai_daily_digest_date')->nullable()->after('ai_daily_digest');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_daily_digest', 'ai_daily_digest_date']);
        });
    }
};
