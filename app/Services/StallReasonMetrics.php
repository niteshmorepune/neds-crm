<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "what's currently tagged as stalling" — every
 * open Lead/Deal with a stall_reason set, each row carrying its own real
 * lastTouchedAt() (notes/activity/calls, not a hand-rolled subset), so
 * ObjectionFollowUpDueSource (the Next Action banner nudge) and the
 * Stalling page (Phase 3, list + team breakdown) can never silently
 * disagree on what counts as stale or who's included. $forUserId scopes to
 * one person's own book — a Lead by owner_id OR telecaller_id, a Deal by
 * owner_id only (Deals have no telecaller concept) — omit it for the
 * whole-team view.
 */
class StallReasonMetrics
{
    /**
     * @return Collection<int, array{subject_type: string, subject_id: int, name: string, stall_reason: StallReason, last_touch: Carbon, days_stale: int, owner_name: ?string, url: string}>
     */
    public function all(?int $forUserId = null): Collection
    {
        return collect([
            ...$this->stallingLeads($forUserId)->all(),
            ...$this->stallingDeals($forUserId)->all(),
        ])->sortBy('last_touch')->values();
    }

    /**
     * @return Collection<int, array{subject_type: string, subject_id: int, name: string, stall_reason: StallReason, last_touch: Carbon, days_stale: int, owner_name: ?string, url: string}>
     */
    public function stallingLeads(?int $forUserId = null): Collection
    {
        return Lead::query()
            ->whereNotNull('stall_reason')
            ->whereIn('status', LeadStatus::openValues())
            ->when($forUserId, fn ($q) => $q->where(fn ($q2) => $q2->where('owner_id', $forUserId)->orWhere('telecaller_id', $forUserId)))
            ->with('owner:id,name')
            ->get()
            ->map(fn (Lead $lead) => $this->row(Lead::class, $lead->id, $lead->name, $lead->stall_reason, $lead->lastTouchedAt(), $lead->owner?->name, route('leads.show', $lead->id)))
            ->pipe(fn ($mapped) => collect($mapped->all()));
    }

    /**
     * @return Collection<int, array{subject_type: string, subject_id: int, name: string, stall_reason: StallReason, last_touch: Carbon, days_stale: int, owner_name: ?string, url: string}>
     */
    public function stallingDeals(?int $forUserId = null): Collection
    {
        return Deal::query()
            ->whereNotNull('stall_reason')
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->when($forUserId, fn ($q) => $q->where('owner_id', $forUserId))
            ->with(['owner:id,name', 'customer:id,company_name'])
            ->get()
            ->map(fn (Deal $deal) => $this->row(Deal::class, $deal->id, $deal->customer?->company_name ?? $deal->title, $deal->stall_reason, $deal->lastTouchedAt(), $deal->owner?->name, route('deals.show', $deal->id)))
            ->pipe(fn ($mapped) => collect($mapped->all()));
    }

    /**
     * The coaching-signal rollup: how many currently-stalling records fall
     * under each reason. A live snapshot, not a time-windowed trend — there
     * is no "tagged_at" timestamp to trend against yet (deliberately not
     * added for Phase 3; revisit if this rollup proves worth trending).
     *
     * @return array<string, int> keyed by StallReason::value, only reasons actually present
     */
    public function countsByReason(?int $forUserId = null): array
    {
        return $this->all($forUserId)
            ->groupBy(fn (array $row) => $row['stall_reason']->value)
            ->map->count()
            ->sortDesc()
            ->all();
    }

    /**
     * @return array{subject_type: string, subject_id: int, name: string, stall_reason: StallReason, last_touch: Carbon, days_stale: int, owner_name: ?string, url: string}
     */
    private function row(string $subjectType, int $subjectId, string $name, StallReason $stallReason, Carbon $lastTouch, ?string $ownerName, string $url): array
    {
        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'name' => $name,
            'stall_reason' => $stallReason,
            'last_touch' => $lastTouch,
            'days_stale' => (int) $lastTouch->diffInDays(now()),
            'owner_name' => $ownerName,
            'url' => $url,
        ];
    }
}
