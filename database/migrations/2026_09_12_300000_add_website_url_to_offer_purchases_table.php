<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors visibility_audit_purchases.gbp_url — captured at checkout only
 * for the Website Growth Audit offer (OfferKey::collectsWebsiteUrl()), so
 * the payer doesn't have to be asked for their website URL again if it's
 * missing when they reach the offer page. See App\Jobs\RecordOfferPurchase
 * for the write-back onto the matched Lead's own website_url field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_purchases', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('payer_email');
        });
    }

    public function down(): void
    {
        Schema::table('offer_purchases', function (Blueprint $table) {
            $table->dropColumn('website_url');
        });
    }
};
