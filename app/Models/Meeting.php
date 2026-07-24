<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'google_event_id',
        'title',
        'occurred_at',
        'duration_minutes',
        'attendees',
        'drive_recording_url',
        'drive_transcript_url',
        'raw_transcript',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'duration_minutes' => 'integer',
            'attendees' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meetable(): MorphTo
    {
        return $this->morphTo();
    }
}
