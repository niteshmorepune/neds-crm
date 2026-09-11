<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight event log for the Meta Ads offer/recommendation funnel —
 * mirrors visibility_audit_funnel_events' shape, generalized across all 4
 * offers, so App\Services\OfferFunnelMetrics can report by goal/budget/
 * offer/date without relying only on the latest-value columns on `leads`
 * (which can't show history across repeat visits or a regenerated
 * recommendation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_funnel_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('offer_key')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_funnel_events');
    }
};
