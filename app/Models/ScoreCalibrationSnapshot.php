<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per score band per period -- written monthly by
 * App\Console\Commands\SnapshotScoreCalibration, read by the Score
 * Calibration report's trend view (App\Services\ScoreCalibrationMetrics::
 * trend()). Measurement only: nothing reads this table into any
 * scoring/routing decision.
 */
class ScoreCalibrationSnapshot extends Model
{
    protected $fillable = [
        'period_start',
        'band',
        'total',
        'converted',
        'lost',
        'conversion_rate',
        'avg_days_to_close_converted',
        'median_days_to_close_converted',
        'avg_days_to_close_lost',
        'median_days_to_close_lost',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
        ];
    }
}
