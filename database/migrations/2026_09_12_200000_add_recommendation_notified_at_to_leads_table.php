<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency guard for the unified offer-funnel first-touch dispatch (see
 * App\Actions\GenerateLeadRecommendation) — mirrors visibility_audit_invited_at
 * exactly, just for the 3 non-GBP offers' own first-touch job
 * (App\Jobs\SendOfferRecommendationReadyJob). A lead recommended into
 * GbpAudit still uses visibility_audit_invited_at (untouched) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('recommendation_notified_at')->nullable()->after('offer_clicked_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('recommendation_notified_at');
        });
    }
};
