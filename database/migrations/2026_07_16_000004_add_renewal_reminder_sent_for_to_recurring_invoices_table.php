<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the end_date a renewal reminder was last sent for (not just a
 * timestamp) so a template renewed to a later end_date correctly triggers a
 * fresh reminder — see App\Console\Commands\SendContractRenewalReminders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->date('renewal_reminder_sent_for')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn('renewal_reminder_sent_for');
        });
    }
};
