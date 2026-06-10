<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * The user who performed the action (null for system/console actions).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The model the activity was recorded against.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
