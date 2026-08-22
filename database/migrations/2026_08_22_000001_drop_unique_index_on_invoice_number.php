<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A DB-level unique index can't tell a soft-deleted invoice's
     * invoice_number apart from a live one, so a deleted invoice
     * permanently blocked its (real, external Hitech) number from ever
     * being reused — the app-level validation in InvoiceLog{Store,Update}
     * Request already excludes trashed rows via ->withoutTrashed(), so
     * that's now the sole place uniqueness (among live invoices) is
     * enforced.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('invoice_number');
        });
    }
};
