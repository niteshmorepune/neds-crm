<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin/manager-configured lead routing rule. Exactly one of utm_campaign /
 * service_id / va_paid is set — see LeadAssignmentRuleRequest for the XOR
 * validation. Campaign/service rules are checked by
 * LeadObserver::autoAssign() before its least-loaded round-robin fallback,
 * campaign match taking priority over service match. A va_paid rule is
 * checked separately, by RecordVisibilityAuditPurchase, at the moment a lead
 * pays for the Visibility Audit offer — see that job's own docblock for why
 * it can't reuse autoAssign()'s per-lead-creation flow.
 */
class LeadAssignmentRule extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'utm_campaign',
        'service_id',
        'va_paid',
        'assigned_user_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'va_paid' => 'boolean',
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

    /**
     * This rule's target user, but only if still an eligible active Sales
     * rep — a rule whose target was later deactivated or role-changed must
     * never silently route to someone ineligible. Shared by
     * LeadObserver::matchRule() and RecordVisibilityAuditPurchase so both
     * call sites enforce the exact same eligibility check.
     */
    public function eligibleAssignee(): ?User
    {
        $user = $this->assignedUser;

        return $user && $user->is_active && $user->role === UserRole::Sales ? $user : null;
    }
}
