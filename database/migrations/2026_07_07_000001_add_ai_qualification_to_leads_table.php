<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI lead qualification (Phase A of the AI lead funnel): extends the existing
 * 0-100 score with a budget band, urgency, and a short service-fit note, all
 * written by the same ScoreLead job. Nullable — unset until scored, or when
 * AI is disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('ai_budget_band')->nullable()->after('ai_scored_at');
            $table->string('ai_urgency')->nullable()->after('ai_budget_band');
            $table->string('ai_service_fit')->nullable()->after('ai_urgency');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['ai_budget_band', 'ai_urgency', 'ai_service_fit']);
        });
    }
};
