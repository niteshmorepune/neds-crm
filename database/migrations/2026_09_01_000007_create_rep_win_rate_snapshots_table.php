<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per rep per period per breakdown dimension, written monthly by
 * App\Console\Commands\SnapshotRepWinRates. Measurement only -- nothing
 * reads this table into LeadAssignmentRule or any other routing decision;
 * it exists purely so a win-rate trend is there to evaluate once there's
 * enough of it, per this phase's own "not yet" framing.
 *
 * `dimension` is 'overall' (one row per rep per period, dimension_value
 * null), 'source' (one row per rep+period+LeadSource value), or
 * 'score_band' (one row per rep+period+Cold/Warm/Hot/no_score band) — a
 * generalized shape so a future breakdown can be added without a new
 * table. dimension_value is nullable (used for 'overall') -- same
 * distinct-NULLs caveat as sales_targets' own migration: MySQL won't
 * enforce cross-NULL uniqueness at the DB level, so the real guarantee
 * comes from always writing through the same keyed updateOrCreate call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rep_win_rate_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start'); // always the 1st of the month
            $table->string('dimension'); // overall|source|score_band
            $table->string('dimension_value')->nullable(); // null for 'overall'; a LeadSource value or score band otherwise
            $table->unsignedInteger('won_count');
            $table->unsignedInteger('lost_count');
            $table->unsignedTinyInteger('win_rate')->nullable(); // 0-100, null when won+lost = 0 (nothing to compute a rate from)
            $table->timestamps();

            $table->unique(['user_id', 'period_start', 'dimension', 'dimension_value'], 'rep_win_rate_snapshots_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rep_win_rate_snapshots');
    }
};
