<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link generated invoices back to their recurring template so the Revenue
 * Report can split recurring vs one-time income. Null = ad-hoc/one-time invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recurring_invoice_id')->nullable()->after('quotation_id')
                ->constrained('recurring_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_invoice_id');
        });
    }
};
