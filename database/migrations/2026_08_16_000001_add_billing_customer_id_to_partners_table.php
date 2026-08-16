<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reseller billing: when set, any Customer referred by this partner should
 * be GST-invoiced to this Customer instead of directly — e.g. Brand-Whiz
 * resells NEDS's service to its own clients, so the real GST invoice goes
 * to Brand Whiz, not each individual client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->foreignId('billing_customer_id')->nullable()->after('commission_rate')
                ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_customer_id');
        });
    }
};
