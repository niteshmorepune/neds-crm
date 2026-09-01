<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per score band per period, written by App\Console\Commands\
 * SnapshotScoreCalibration (measurement only -- nothing reads this table
 * into any scoring/routing decision). Recorded as a time series, not
 * overwritten, so calibration drift is something to actually look back at.
 * Same idempotency shape as incentive_statements: unique on
 * (period_start, band), updateOrCreate keyed the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_calibration_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('period_start'); // always the 1st of the month
            $table->string('band'); // hot|warm|cold|no_score
            $table->unsignedInteger('total');
            $table->unsignedInteger('converted');
            $table->unsignedInteger('lost');
            $table->unsignedTinyInteger('conversion_rate'); // 0-100
            $table->unsignedInteger('avg_days_to_close_converted')->nullable();
            $table->unsignedInteger('median_days_to_close_converted')->nullable();
            $table->unsignedInteger('avg_days_to_close_lost')->nullable();
            $table->unsignedInteger('median_days_to_close_lost')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_calibration_snapshots');
    }
};
