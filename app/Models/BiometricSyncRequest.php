<?php

namespace App\Models;

use App\Enums\BiometricSyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricSyncRequest extends Model
{
    protected $fillable = [
        'requested_by_id', 'requested_at', 'completed_at', 'status', 'summary', 'error',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => BiometricSyncStatus::class,
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
