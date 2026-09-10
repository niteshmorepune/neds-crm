<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Enums\VoiceTranscriptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'callable_type', 'callable_id', 'direction',
        'duration_minutes', 'outcome', 'notes', 'called_at',
        'next_action', 'follow_up_at', 'wadesk_call_id',
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

    /**
     * A follow-up is due only while it's still actionable — real incident
     * (2026-09-08): the Next Action banner, My Day, the Telecaller
     * dashboard tile, and the reminder notification command all kept
     * prompting a callback on a lead that had since been marked Lost,
     * since none of them checked the lead's current status, only the
     * timestamp. Excludes a Lead callable whose status is Lost; a
     * Customer callable (which has no equivalent terminal "dead" status)
     * passes through unfiltered.
     */
    public function scopeFollowUpDue(Builder $query, ?Carbon $asOf = null): Builder
    {
        return $query->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', $asOf ?? now())
            ->where(fn (Builder $q) => $q
                // SQL's != is never true against a NULL column, so a
                // callable-less CallLog needs its own explicit branch here —
                // whereNot('callable_type', Lead::class) alone silently
                // excluded every row with callable_type IS NULL.
                ->whereNull('callable_type')
                ->orWhereNot('callable_type', Lead::class)
                ->orWhereHas('callable', fn (Builder $lead) => $lead->where('status', '!=', LeadStatus::Lost->value)));
    }
}
