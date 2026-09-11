<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields backing the Meta Ads recommendation funnel (App\Support\
 * OfferRecommendationMatrix onward) — see CLAUDE.md decisions log.
 * `goal` already exists (2026-09-08); `budget_range` is its Q2 counterpart.
 * `recommendation_token` is the unguessable public identifier the
 * personalized recommendation page is reached by (lazily generated, same
 * pattern as Quotation::public_token) — never the bare lead id, so the
 * page can't be walked by incrementing a URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('budget_range')->nullable()->after('goal');
            $table->string('recommendation_key')->nullable()->after('estimated_value');
            $table->string('recommendation_offer_key')->nullable()->after('recommendation_key');
            $table->uuid('recommendation_token')->nullable()->unique()->after('recommendation_offer_key');
            $table->timestamp('recommendation_generated_at')->nullable()->after('recommendation_token');
            $table->timestamp('recommendation_viewed_at')->nullable()->after('recommendation_generated_at');
            $table->timestamp('offer_viewed_at')->nullable()->after('recommendation_viewed_at');
            $table->timestamp('offer_clicked_at')->nullable()->after('offer_viewed_at');

            $table->index('budget_range');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['budget_range']);
            $table->dropColumn([
                'budget_range',
                'recommendation_key',
                'recommendation_offer_key',
                'recommendation_token',
                'recommendation_generated_at',
                'recommendation_viewed_at',
                'offer_viewed_at',
                'offer_clicked_at',
            ]);
        });
    }
};
