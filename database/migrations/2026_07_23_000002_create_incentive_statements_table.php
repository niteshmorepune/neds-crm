<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One locked row per Sales user per calendar month, written by
 * App\Console\Commands\FinalizeIncentives on the 1st of the following month.
 * Before finalization the same figures are computed live (never stored) by
 * App\Services\IncentiveCalculator — this table is only the closed ledger
 * payroll relies on, so an edited/reopened Deal after month-close can never
 * change a past month's number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start'); // always the 1st of the month
            $table->unsignedBigInteger('sales_value'); // paise, before tax
            $table->unsignedBigInteger('individual_incentive'); // paise
            $table->boolean('team_bonus_eligible')->default(false);
            $table->unsignedBigInteger('team_bonus_share')->default(0); // paise
            $table->unsignedBigInteger('total_incentive'); // paise
            $table->timestamp('finalized_at');
            $table->timestamps();

            $table->unique(['user_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_statements');
    }
};
