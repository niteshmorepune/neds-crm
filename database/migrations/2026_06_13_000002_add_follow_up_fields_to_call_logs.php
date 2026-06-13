<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('next_action')->nullable()->after('notes');
            $table->timestamp('follow_up_at')->nullable()->after('next_action');
            $table->timestamp('follow_up_notified_at')->nullable()->after('follow_up_at');

            $table->index('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex(['follow_up_at']);
            $table->dropColumn(['next_action', 'follow_up_at', 'follow_up_notified_at']);
        });
    }
};
