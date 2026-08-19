<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real production data (Prajakta Dahake's 9 referred clients) surfaced that
 * a referral share is often a FIXED ₹ amount per month, not a percentage of
 * billing — e.g. Ampra Biobact is billed ₹15,000+GST but Prajakta's share is
 * a flat ₹10,000 regardless, not 66.7% (which would silently drift the
 * moment billing changes). referral_share_type null = Percentage (this
 * project's original design, still valid for a client whose split genuinely
 * scales with billing) — no backfill needed for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('referral_share_type')->nullable()->after('referral_share_rate');
            $table->unsignedBigInteger('referral_share_fixed_amount')->nullable()->after('referral_share_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['referral_share_type', 'referral_share_fixed_amount']);
        });
    }
};
