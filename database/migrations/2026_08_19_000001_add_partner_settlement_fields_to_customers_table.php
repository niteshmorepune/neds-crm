<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client referral settlement setup — only meaningful when
 * referring_partner_id is set. Deliberately separate from
 * partners.commission_rate (that one is a one-time % of Deal.value at Won,
 * see PartnerCommissionCalculator) — this is a recurring, per-client rate,
 * since the same partner can have different clients at different splits.
 * partner_collection_mode null = NedsCollects, matching every existing
 * client's real behavior today, so no backfill is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('partner_collection_mode')->nullable()->after('referring_partner_id');
            $table->decimal('referral_share_rate', 5, 2)->nullable()->after('partner_collection_mode');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['partner_collection_mode', 'referral_share_rate']);
        });
    }
};
