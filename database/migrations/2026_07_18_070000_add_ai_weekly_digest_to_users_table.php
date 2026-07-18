<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ai_weekly_digest')->nullable()->after('ai_daily_digest_date');
            $table->date('ai_weekly_digest_date')->nullable()->after('ai_weekly_digest');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_weekly_digest', 'ai_weekly_digest_date']);
        });
    }
};
