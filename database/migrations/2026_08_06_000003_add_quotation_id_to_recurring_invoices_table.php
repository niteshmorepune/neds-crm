<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Traceability back to the quotation this recurring invoice was
        // generated from, when it was created via that flow rather than
        // standalone. Nullable — most recurring invoices aren't quotation-
        // derived — and nulled (not cascaded) if the quotation is later
        // deleted, since the recurring invoice itself should keep running.
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
        });
    }
};
