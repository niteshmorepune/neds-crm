<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI lead scoring (Phase 5): a 0–100 score with a one-line reason, written by
 * the ScoreLead job. Nullable — leads are unscored until the job runs (or stay
 * unscored when AI is disabled).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('ai_score')->nullable()->after('status');
            $table->string('ai_score_reason')->nullable()->after('ai_score');
            $table->timestamp('ai_scored_at')->nullable()->after('ai_score_reason');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['ai_score', 'ai_score_reason', 'ai_scored_at']);
        });
    }
};
