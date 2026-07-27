<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Monday run of App\Console\Commands\SendWeeklyOwnerDigest —
 * persists the metrics snapshot (and AI summary, when available) so the
 * owner's "Your week ahead" digest can be reviewed as history/trends
 * instead of being overwritten on the User row every week. Recorded even
 * when AI_ENABLED is off (summary stays null) since the metrics themselves
 * don't depend on AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_digests', function (Blueprint $table) {
            $table->id();
            $table->date('digest_date')->unique();
            $table->text('summary')->nullable();
            $table->unsignedInteger('pipeline_open_deals_count');
            $table->unsignedBigInteger('pipeline_open_value'); // paise
            $table->unsignedInteger('deals_won_count');
            $table->unsignedInteger('deals_lost_count');
            $table->unsignedBigInteger('mrr_total'); // paise
            $table->unsignedInteger('recurring_contracts_expiring_count');
            $table->unsignedBigInteger('cash_expected_this_month'); // paise
            $table->unsignedBigInteger('cash_expected_three_months'); // paise
            $table->unsignedBigInteger('receivables_total_outstanding'); // paise
            $table->unsignedBigInteger('receivables_overdue_ninety_plus_days'); // paise
            $table->unsignedInteger('client_radar_flagged_count');
            $table->unsignedInteger('client_radar_low_satisfaction_count');
            $table->unsignedInteger('client_radar_overdue_invoice_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_digests');
    }
};
