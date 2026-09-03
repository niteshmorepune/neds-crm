<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lets a user temporarily suppress one NextActionSource's prompt for one
 * specific subject (e.g. "stop showing me this lead's call reminder for 30
 * minutes") without resolving it — the engine skips any subject with an
 * active (unexpired) row here. Snoozing is purely a per-viewer convenience,
 * never a way to hide something from oversight (same convention as Team
 * Nudges' own per-viewer snooze).
 */
class NextActionSnooze extends Model
{
    protected $fillable = [
        'user_id',
        'source_key',
        'subject_type',
        'subject_id',
        'snoozed_until',
    ];

    protected $casts = [
        'snoozed_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
