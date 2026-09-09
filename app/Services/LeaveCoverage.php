<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\SyncLeaveCoverToWadeskJob;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides whether an approved leave request needs a covering teammate
 * picked (so their wadesk.in WhatsApp chats don't sit silently unwatched
 * while they're out) and who's eligible to cover — mirrors
 * UserUpdateRequest's deactivation-handover rule (required, not a silent
 * no-op) and LeadReassignRequest's "same-role peer only" eligibility rule,
 * applied here to Leave approval instead.
 */
class LeaveCoverage
{
    /**
     * @return Builder<Lead>
     */
    public function openLeadsQuery(User $user): Builder
    {
        return Lead::where(fn (Builder $q) => $q->where('owner_id', $user->id)->orWhere('telecaller_id', $user->id))
            ->whereIn('status', LeadStatus::openValues());
    }

    public function isRequiredFor(User $user): bool
    {
        return $user->hasRole(UserRole::Sales, UserRole::Telecaller) && $this->openLeadsQuery($user)->exists();
    }

    /**
     * Active users sharing at least one of the leave-taker's Sales/
     * Telecaller roles (primary or additional), excluding the leave-taker
     * themselves. A user with neither role has nobody eligible to pick.
     *
     * @return Collection<int, User>
     */
    public function eligibleCovers(User $user): Collection
    {
        $relevantRoles = collect([UserRole::Sales, UserRole::Telecaller])
            ->filter(fn (UserRole $role) => $user->hasRole($role))
            ->values();

        if ($relevantRoles->isEmpty()) {
            return collect();
        }

        return User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->withAnyRole(...$relevantRoles->all())
            ->orderBy('name')
            ->get();
    }

    /**
     * Dispatches one SyncLeaveCoverToWadeskJob per currently-open lead the
     * leave-taker owns or is telecaller for — the job itself recomputes
     * whether cover should be active or expired from the leave request's
     * live state, so calling this repeatedly (immediately on approval, and
     * again from the periodic scheduled command to catch leads created
     * mid-leave) is always safe.
     */
    public function dispatchSync(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->covering_user_id === null || $leaveRequest->user === null) {
            return;
        }

        $this->openLeadsQuery($leaveRequest->user)->pluck('id')->each(
            fn (int $leadId) => SyncLeaveCoverToWadeskJob::dispatch($leadId, $leaveRequest->id)
        );
    }
}
