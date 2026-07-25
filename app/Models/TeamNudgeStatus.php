<?php

namespace App\Models;

use App\Enums\NudgeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamNudgeStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_nudge_id', 'user_id', 'period_start', 'status',
        'completed_via', 'completed_at', 'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'status' => NudgeStatus::class,
            'completed_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function nudge(): BelongsTo
    {
        return $this->belongsTo(TeamNudge::class, 'team_nudge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrentlySnoozed(): bool
    {
        return $this->status === NudgeStatus::Snoozed
            && $this->snoozed_until !== null
            && $this->snoozed_until->isFuture();
    }
}
