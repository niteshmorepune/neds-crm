<?php

use App\Enums\QuotationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(QuotationStatus::Draft->value);

            // GST snapshot at time of quoting.
            $table->string('place_of_supply_state_code', 2)->nullable();
            $table->boolean('is_intra_state')->default(true);

            // All money in integer paise. round_off is signed.
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('taxable_total')->default(0);
            $table->unsignedBigInteger('cgst_total')->default(0);
            $table->unsignedBigInteger('sgst_total')->default(0);
            $table->unsignedBigInteger('igst_total')->default(0);
            $table->integer('round_off')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            $table->text('terms')->nullable();
            $table->date('validity_date')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('sac_code')->nullable();   // HSN/SAC
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('rate')->default(0);     // paise per unit
            $table->decimal('gst_rate', 5, 2)->default(18);     // percent
            $table->unsignedBigInteger('amount')->default(0);   // paise (quantity * rate)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
