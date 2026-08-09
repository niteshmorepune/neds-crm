<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract & Renewal Dashboard tracks a manual conversation-status pipeline
 * (Not Started → Discussion → Proposal Sent → Negotiation → Renewed/Lost)
 * per recurring template, separate from is_active/dashboardStatus() which
 * are both billing/payment state, not "have we talked to the client about
 * renewing yet." Defaults every existing row to 'not_started' rather than
 * leaving it null, so every list/filter can treat the column as always set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->string('renewal_status')->default('not_started')->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn('renewal_status');
        });
    }
};
