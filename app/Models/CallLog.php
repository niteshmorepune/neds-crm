<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'callable_type', 'callable_id', 'direction',
        'duration_minutes', 'outcome', 'notes', 'called_at',
        'next_action', 'follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CallDirection::class,
            'outcome' => CallOutcome::class,
            'duration_minutes' => 'integer',
            'called_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'follow_up_notified_at' => 'datetime',
        ];
    }

    public function hasFollowUp(): bool
    {
        return $this->follow_up_at !== null;
    }

    public function followUpIsDue(): bool
    {
        return $this->follow_up_at !== null && $this->follow_up_at->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callable(): MorphTo
    {
        return $this->morphTo();
    }
}
