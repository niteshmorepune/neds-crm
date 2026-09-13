<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot retry marker for App\Console\Commands\RetryStrandedOfferRecommendationMessages
 * — set the moment a lead is retried, regardless of whether the retry itself
 * succeeds, so a lead is never automatically retried more than once. Mirrors
 * recommendation_notified_at's own shape exactly, just for "did we attempt a
 * retry" rather than "did the original send succeed."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('recommendation_retry_attempted_at')->nullable()->after('recommendation_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('recommendation_retry_attempted_at');
        });
    }
};
