<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Set only for online (PaymentMode::Gateway) payments. Distinct from
            // the free-text `reference` column so gateway lookups (idempotency
            // guard, webhook reconciliation) can be indexed/unique.
            $table->string('gateway_order_id')->nullable()->after('reference');
            $table->string('gateway_payment_id')->nullable()->unique()->after('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropColumn(['gateway_order_id', 'gateway_payment_id']);
        });
    }
};
