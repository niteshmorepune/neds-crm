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
    ];

    protected function casts(): array
    {
        return [
            'direction' => CallDirection::class,
            'outcome' => CallOutcome::class,
            'duration_minutes' => 'integer',
            'called_at' => 'datetime',
        ];
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
