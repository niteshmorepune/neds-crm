<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client "billed via a third party" support — only meaningful when
 * partner_collection_mode is PartnerCollectionMode::BilledViaThirdParty. A
 * referred client can be routed through a third-party company that has its
 * own tie-up with the referring partner (e.g. NEDS bills "Pulse Orbit
 * Entertainment Pvt Ltd", which in turn bills the actual client) — kept on
 * the CUSTOMER, not the Partner (unlike Partner.billing_customer_id's
 * reseller field), since the same partner can route different referred
 * clients through different third parties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('billed_via_customer_id')->nullable()->after('partner_collection_mode')
                ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billed_via_customer_id');
        });
    }
};
