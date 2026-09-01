<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Lead to Won" Phase 3, Task 3 -- capture only, not a scoring input.
 * Computed once in App\Jobs\RecordVisibilityAuditPurchase::handle(), as the
 * delta between the matched Lead's first tracked landing-page view
 * (visibility_audit_funnel_events, event_type=landing_viewed) and this
 * purchase's own created_at. Null when no such event exists for the
 * matched lead (a purchase from before this funnel was tracked, or one
 * whose payer never passed through the tracked landing redirect) -- never
 * backfilled for existing rows, same going-forward-only precedent as
 * leads.lost_at and deals.ai_suggested_lost_reason.
 *
 * Deliberately NOT read by ScoreLead or any other scoring/routing
 * decision -- see the job's own updated docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->unsignedInteger('time_to_payment_minutes')->nullable()->after('amount_paise');
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->dropColumn('time_to_payment_minutes');
        });
    }
};
