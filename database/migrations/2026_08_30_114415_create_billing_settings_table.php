<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table holding the admin-editable default SAC/HSN code applied
 * to a new Quotation/Invoice/Recurring Invoice line item. Modeled as a
 * singleton via BillingSetting::current() (firstOrCreate), same pattern as
 * incentive_settings, rather than a generic key-value settings table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_sac_code', 20)->default('998314');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
