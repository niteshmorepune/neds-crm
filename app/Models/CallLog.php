<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\VoiceTranscriptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'callable_type', 'callable_id', 'direction',
        'duration_minutes', 'outcome', 'notes', 'called_at',
        'next_action', 'follow_up_at',
        'voice_transcript_status', 'voice_transcript', 'voice_transcribed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CallDirection::class,
            'outcome' => CallOutcome::class,
            'voice_transcript_status' => VoiceTranscriptStatus::class,
            'duration_minutes' => 'integer',
            'called_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'follow_up_notified_at' => 'datetime',
            'voice_transcribed_at' => 'datetime',
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function hasVoiceNote(): bool
    {
        return $this->voice_transcript_status !== null;
    }
}
