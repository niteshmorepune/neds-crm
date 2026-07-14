<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->boolean('is_gst_exempt')->default(false)->after('discount');
        });

        // Generation used to read the customer's live gst_exempt flag every
        // cycle; now it reads this per-template column instead (so it can be
        // overridden independently, like Quotation/Invoice already allow).
        // Backfill from the customer's current setting so an already
        // non-GST customer's existing template doesn't silently start
        // charging GST on its next invoice.
        DB::table('recurring_invoices')
            ->join('customers', 'customers.id', '=', 'recurring_invoices.customer_id')
            ->where('customers.gst_exempt', true)
            ->update(['recurring_invoices.is_gst_exempt' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn('is_gst_exempt');
        });
    }
};
