<?php

namespace App\Models;

use App\Enums\MeetingPlatform;
use App\Enums\MeetingSummaryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'google_event_id',
        'meet_link',
        'platform',
        'title',
        'occurred_at',
        'duration_minutes',
        'attendees',
        'drive_recording_url',
        'drive_transcript_url',
        'raw_transcript',
        'ai_summary_status',
        'ai_summary',
        'ai_summarized_at',
        'requested_by_client',
        'client_note',
    ];

    protected function casts(): array
    {
        return [
            'platform' => MeetingPlatform::class,
            'occurred_at' => 'datetime',
            'duration_minutes' => 'integer',
            'attendees' => 'array',
            'ai_summary_status' => MeetingSummaryStatus::class,
            'ai_summarized_at' => 'datetime',
            'requested_by_client' => 'boolean',
        ];
    }

    /**
     * True only for a real Google Meet integration row — a manually-logged
     * external meeting (Zoom/Teams/etc.) has no real Calendar event backing
     * it, so "sync recording & transcript" (which calls Google's API) must
     * never be offered for it.
     */
    public function isGoogleMeetImport(): bool
    {
        return $this->platform === MeetingPlatform::GoogleMeet;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meetable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Internal team members invited alongside the client (see
     * MeetingImport::createMeeting()) — never the client, who is invited via
     * the Google Calendar event's own attendee list, not this table.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }
}
