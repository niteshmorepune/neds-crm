<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A completed-or-attempted purchase of one of the 3 new entry offers
 * (App\Enums\OfferKey — GbpAudit deliberately excluded, it keeps its own
 * pre-existing visibility_audit_purchases table) via the new in-app
 * Razorpay Orders checkout (App\Http\Controllers\OfferCheckoutController).
 * Created as `pending` the moment an order is opened so a price/offer is
 * always resolvable server-side from THIS row by the time a webhook or the
 * synchronous verify() call arrives — never from anything the browser sends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('offer_key');
            $table->unsignedInteger('price_paise');
            $table->string('status')->default('pending');
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('payer_email')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['offer_key', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_purchases');
    }
};
