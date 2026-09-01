<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per rep per period per breakdown dimension ('overall', 'source',
 * or 'score_band') -- written monthly by App\Console\Commands\
 * SnapshotRepWinRates, read by the Rep Win Rate report
 * (App\Services\RepWinRateMetrics::forPeriod()). Measurement only: nothing
 * reads this table into LeadAssignmentRule or any other routing decision.
 */
class RepWinRateSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'dimension',
        'dimension_value',
        'won_count',
        'lost_count',
        'win_rate',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
