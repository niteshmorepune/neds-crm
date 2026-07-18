<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * "Deals like this one" — surfaces past closed deals a rep can use as
 * precedent while working an open deal. Similarity definition confirmed
 * with the owner via AskUserQuestion before building (flagged in the
 * backlog as needing one worked out): service is a hard filter (a Website
 * Dev deal is never comparable to an SEO deal), candidates are ranked by
 * how close their value is to this deal's, and a matching lead source only
 * breaks a tie — it never excludes a candidate. This degrades gracefully on
 * a still-young dataset: a strict service+value-band+source match would
 * often show nothing at all, whereas "closest value, same service" always
 * surfaces the best available precedent if any exists.
 *
 * Candidate pool is Won AND Lost (not Won-only) — the owner confirmed a rep
 * should see the realistic pattern for this deal's profile ("2 won, 1
 * lost"), not just a curated highlight reel of successes.
 */
class SimilarDealFinder
{
    /**
     * @return Collection<int, Deal>
     */
    public function find(Deal $deal, int $limit = 3): Collection
    {
        // Nothing to compare against without a service on this deal itself.
        if ($deal->service_id === null) {
            return new Collection;
        }

        $sourceValue = $deal->lead?->source?->value;

        return Deal::query()
            ->where('id', '!=', $deal->id)
            ->where('service_id', $deal->service_id)
            ->whereIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->with(['customer:id,company_name', 'lead:id,source'])
            ->get()
            ->map(function (Deal $candidate) use ($deal, $sourceValue) {
                $candidate->setAttribute('value_diff', abs($candidate->value - $deal->value));
                $candidate->setAttribute('source_matches', $sourceValue !== null && $candidate->lead?->source?->value === $sourceValue);

                return $candidate;
            })
            // Closest value wins; a matching source only breaks a tie.
            // Falling back to most-recent as the final tiebreak keeps the
            // result deterministic rather than depending on DB row order.
            ->sortBy([
                ['value_diff', 'asc'],
                fn (Deal $a, Deal $b) => ($b->source_matches <=> $a->source_matches),
                ['created_at', 'desc'],
            ])
            ->take($limit)
            ->values();
    }
}
