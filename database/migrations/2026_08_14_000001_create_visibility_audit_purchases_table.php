<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visibility_audit_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('tier')->nullable();
            $table->unsignedInteger('amount_paise');
            $table->string('razorpay_payment_id')->unique();
            $table->string('razorpay_order_id')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('payer_email')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visibility_audit_purchases');
    }
};
