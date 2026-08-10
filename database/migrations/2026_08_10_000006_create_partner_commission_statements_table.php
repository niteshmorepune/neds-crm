<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One locked row per Partner per calendar month, written by
 * App\Console\Commands\FinalizePartnerCommissions on the 1st of the
 * following month — mirrors incentive_statements exactly. Before
 * finalization the same figures are computed live (never stored) by
 * App\Services\PartnerCommissionCalculator. commission_rate is snapshotted
 * here (not just read live off partners.commission_rate) because a rate
 * can change after the fact — a past month's locked statement must keep
 * the rate that actually applied when it was earned.
 *
 * paid_at/paid_by track a manual "mark as paid" ledger only — no live
 * payment processing, per the Tier 1 scoping decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_commission_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->date('period_start'); // always the 1st of the month
            $table->unsignedBigInteger('referred_value'); // paise, before tax
            $table->decimal('commission_rate', 5, 2); // snapshot, % at finalize time
            $table->unsignedBigInteger('commission_amount'); // paise
            $table->timestamp('finalized_at');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commission_statements');
    }
};
