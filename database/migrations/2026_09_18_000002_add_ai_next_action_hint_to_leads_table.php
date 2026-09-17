<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('ai_next_action_hint')->nullable()->after('ai_detected_next_action');
            $table->timestamp('ai_next_action_generated_at')->nullable()->after('ai_next_action_hint');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['ai_next_action_hint', 'ai_next_action_generated_at']);
        });
    }
};
