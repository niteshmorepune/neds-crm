<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Note;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Support\Collection;

/**
 * Central Approval Center (Manager panel doc "clarify first" item, scoped
 * via AskUserQuestion). Aggregates every genuinely pending approval
 * workflow that already exists in the app into one place — it does not
 * duplicate any approve/reject logic, each section's actions still go
 * through that type's own existing controller/Livewire component:
 *
 * - Leave requests: LeaveRequestController::approve()/reject() (unchanged).
 * - Project updates: the existing ProjectDailyUpdateReview Livewire
 *   component, embedded per-project — its approve()/discard() methods are
 *   reused verbatim, not reimplemented here.
 * - Quotations: the new QuotationController::approve()/reject()/
 *   requestChanges() actions (see 2026_08_10_000002 migration).
 *
 * Two items from the original ask were deliberately left out after
 * investigation (confirmed via AskUserQuestion): "Content" approval
 * happens entirely inside the separate SMDost app — nothing is ever
 * pending inside this CRM for it. "Client requests" has no existing
 * concept anywhere in this app (no model, no portal form) — building it
 * would be a new feature, not an aggregation.
 */
class ApprovalCenterMetrics
{
    /**
     * @return Collection<int, LeaveRequest>
     */
    public function pendingLeaveRequests(): Collection
    {
        return LeaveRequest::pending()->with('user')->orderBy('start_date')->get();
    }

    /**
     * @return Collection<int, Quotation>
     */
    public function pendingQuotations(): Collection
    {
        return Quotation::pendingApproval()->with('customer')->latest()->get();
    }

    /**
     * Projects that have at least one AI-drafted daily update still
     * awaiting review — same query ProjectDailyUpdateReview's own
     * pendingDrafts() uses, just at the project-selection level.
     *
     * @return Collection<int, Project>
     */
    public function projectsWithPendingUpdates(): Collection
    {
        return Project::whereHas('notes', function ($query) {
            $query->where('ai_generated', true)->where('visible_to_client', false);
        })->with('customer')->get();
    }

    public function pendingProjectUpdateCount(): int
    {
        return Note::where('ai_generated', true)->where('visible_to_client', false)->count();
    }

    public function totalCount(): int
    {
        return $this->pendingLeaveRequests()->count()
            + $this->pendingQuotations()->count()
            + $this->pendingProjectUpdateCount();
    }
}
