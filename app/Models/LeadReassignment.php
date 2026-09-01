<?php

namespace App\Models;

use App\Enums\LeadReassignmentReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable log written by App\Actions\ReassignLead — one row per
 * reassignment, alongside (not replacing) the visible Note it also writes.
 * Powers the Reassignment Analytics report; nothing else reads this table.
 */
class LeadReassignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'from_user_id',
        'to_user_id',
        'reassigned_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => LeadReassignmentReason::class,
            'created_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function reassignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_by');
    }
}
