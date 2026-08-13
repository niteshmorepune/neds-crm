<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin/manager-configured lead routing rule. Exactly one of utm_campaign /
 * service_id is set — see LeadAssignmentRuleRequest for the XOR validation.
 * Checked by LeadObserver::autoAssign() before its least-loaded round-robin
 * fallback, campaign match taking priority over service match.
 */
class LeadAssignmentRule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'utm_campaign',
        'service_id',
        'assigned_user_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
