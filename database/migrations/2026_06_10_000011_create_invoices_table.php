<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free, concurrency-safe invoice numbering per financial year.
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year')->unique(); // e.g. 2026-27
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('financial_year');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(InvoiceStatus::Draft->value);

            $table->date('issue_date');
            $table->date('due_date')->nullable();

            $table->string('place_of_supply_state_code', 2)->nullable();
            $table->boolean('is_intra_state')->default(true);

            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('taxable_total')->default(0);
            $table->unsignedBigInteger('cgst_total')->default(0);
            $table->unsignedBigInteger('sgst_total')->default(0);
            $table->unsignedBigInteger('igst_total')->default(0);
            $table->integer('round_off')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index('due_date');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('sac_code')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('rate')->default(0);
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_number_sequences');
    }
};
