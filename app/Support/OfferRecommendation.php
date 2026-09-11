<?php

namespace App\Support;

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferKey;

/**
 * One cell of the 16-cell goal x budget recommendation matrix — see
 * OfferRecommendationMatrix, the single source of truth this is always
 * constructed from. Never build one of these ad hoc in a controller/view.
 */
final readonly class OfferRecommendation
{
    public function __construct(
        public LeadGoal $goal,
        public LeadBudgetRange $budget,
        public string $recommendationKey,
        public string $recommendationName,
        public OfferKey $offerKey,
        public string $headline,
        public string $positioning,
        public string $explanation,
        public string $salesIntent,
    ) {}

    public function offerName(): string
    {
        return $this->offerKey->label();
    }

    public function priceRupees(): int
    {
        return $this->offerKey->price();
    }

    public function cta(): string
    {
        return $this->offerKey->cta();
    }

    public function offerUrl(): string
    {
        return $this->offerKey->url();
    }

    public function deliverable(): string
    {
        return $this->offerKey->deliverable();
    }
}
