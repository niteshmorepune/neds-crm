<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors visibility_audit_funnel_events.nudged_at — lets
 * App\Services\OfferFunnelMetrics mark the SPECIFIC event a recovery nudge
 * was sent for, rather than the lead as a whole, so a lead can be nudged
 * again after a later, fresh drop-off (a new event row, nudged_at still
 * null) without any extra bookkeeping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_funnel_events', function (Blueprint $table) {
            $table->timestamp('nudged_at')->nullable()->after('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('offer_funnel_events', function (Blueprint $table) {
            $table->dropColumn('nudged_at');
        });
    }
};
