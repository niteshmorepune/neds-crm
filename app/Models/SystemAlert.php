<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cooldown marker for App\Console\Commands\MonitorOfferFunnelFailures — one
 * row per alert_key, updated to now() every time that alert actually fires,
 * so the command can check "have I already alerted on this within the
 * cooldown window" without re-scanning logs for the answer.
 */
class SystemAlert extends Model
{
    protected $fillable = [
        'alert_key',
        'last_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_alerted_at' => 'datetime',
        ];
    }
}
