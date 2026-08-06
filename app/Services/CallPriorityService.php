<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Who to call today" for a Sales rep's own book — a ranked list combining
 * contact recency, an overdue/due follow-up on an open deal, and how
 * advanced their most active open deal is. Deliberately plain-rules, no AI:
 * the ranking itself must stay free, instant, and auditable (a rep checking
 * this daily needs to trust exactly why someone is at the top), matching
 * ClientRadarService's own precedent of keeping AI off this kind of hot
 * path. An on-demand "suggested talking point" per client (AiAssistant::
 * suggestCallTalkingPoint, click-triggered) sits on top of this ranking
 * rather than inside it.
 *
 * Deliberately scoped to owner_id = $user->id, not Customer::visibleTo()'s
 * broader "own + unassigned" — this list is about clients that are actually
 * this rep's relationship to maintain, not every client they're merely
 * allowed to see.
 */
class CallPriorityService
{
    private const MAX_RESULTS = 10;

    /** Days since last contact beyond which the recency signal is maxed out. */
    private const RECENCY_CAP_DAYS = 30;

    private const RECENCY_WEIGHT = 50;

    private const FOLLOW_UP_WEIGHT = 30;

    private const STAGE_WEIGHT = 20;

    /**
     * @return Collection<int, array{customer_id: int, company_name: string, days_since_contact: int, follow_up_due: bool, top_stage_label: ?string, top_stage_probability: ?int, score: float, reason: string}>
     */
    public function rankedClients(User $user): Collection
    {
        $customers = Customer::query()
            ->where('owner_id', $user->id)
            ->whereIn('status', [CustomerStatus::Active->value, CustomerStatus::Prospect->value])
            ->with([
                'notes:id,notable_id,notable_type,created_at',
                'callLogs:id,callable_id,callable_type,called_at',
                'tickets:id,customer_id,created_at',
                'deals' => fn ($q) => $q->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
                    ->select(['id', 'customer_id', 'stage', 'next_follow_up_at']),
            ])
            ->get();

        return $customers
            ->map(fn (Customer $customer) => $this->score($customer))
            ->sortByDesc('score')
            ->take(self::MAX_RESULTS)
            ->values();
    }

    /**
     * @return array{customer_id: int, company_name: string, days_since_contact: int, follow_up_due: bool, top_stage_label: ?string, top_stage_probability: ?int, score: float, reason: string}
     */
    private function score(Customer $customer): array
    {
        $lastTouch = collect([
            $customer->notes->max('created_at'),
            $customer->callLogs->max('called_at'),
            $customer->tickets->max('created_at'),
        ])->filter()->max();

        // Never touched: use time since they became a client rather than
        // treating it as instantly maximally urgent — a client onboarded
        // this morning with zero touches isn't neglected.
        $daysSinceContact = (int) ($lastTouch ?? $customer->created_at)->diffInDays(now());
        $recencyScore = min(self::RECENCY_WEIGHT, ($daysSinceContact / self::RECENCY_CAP_DAYS) * self::RECENCY_WEIGHT);

        $followUpDue = $customer->deals->contains(
            fn ($deal) => $deal->next_follow_up_at !== null && $deal->next_follow_up_at->lessThanOrEqualTo(now())
        );
        $followUpScore = $followUpDue ? self::FOLLOW_UP_WEIGHT : 0;

        $topDeal = $customer->deals->sortByDesc(fn ($deal) => $deal->stage->probability())->first();
        $topStage = $topDeal?->stage;
        $stageScore = $topStage ? ($topStage->probability() / 100) * self::STAGE_WEIGHT : 0;

        $reasonParts = [];
        $reasonParts[] = $lastTouch === null
            ? "No contact since becoming a client ({$daysSinceContact}d)"
            : "No contact in {$daysSinceContact} day(s)";
        if ($followUpDue) {
            $reasonParts[] = 'Follow-up due';
        }
        if ($topStage) {
            $reasonParts[] = "Deal in {$topStage->label()} ({$topStage->probability()}%)";
        }

        return [
            'customer_id' => $customer->id,
            'company_name' => $customer->company_name,
            'days_since_contact' => $daysSinceContact,
            'follow_up_due' => $followUpDue,
            'top_stage_label' => $topStage?->label(),
            'top_stage_probability' => $topStage?->probability(),
            'score' => round($recencyScore + $followUpScore + $stageScore, 1),
            'reason' => implode(' · ', $reasonParts),
        ];
    }
}
