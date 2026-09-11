<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferKey;
use App\Support\OfferRecommendationMatrix;

// ──────────────────────────────────────────────────────────────────────────
// All 16 goal x budget combinations — CLAUDE.md's 2026-09-12 decisions log
// entry / the spec's own section 5 is the single source of truth for these
// exact (offerKey, price) pairs. Never infer one from the other.
// ──────────────────────────────────────────────────────────────────────────

dataset('recommendation_matrix', [
    'generate-leads + under-3000' => [LeadGoal::GenerateLeads, LeadBudgetRange::Under3000, OfferKey::LeadGenerationAudit, 299],
    'generate-leads + 3000-6000' => [LeadGoal::GenerateLeads, LeadBudgetRange::ThreeToSix, OfferKey::LeadGenerationAudit, 299],
    'generate-leads + 6000-12000' => [LeadGoal::GenerateLeads, LeadBudgetRange::SixToTwelve, OfferKey::GrowthStrategy, 999],
    'generate-leads + 12000-plus' => [LeadGoal::GenerateLeads, LeadBudgetRange::TwelvePlus, OfferKey::GrowthStrategy, 999],

    'rank-google + under-3000' => [LeadGoal::RankHigher, LeadBudgetRange::Under3000, OfferKey::GbpAudit, 120],
    'rank-google + 3000-6000' => [LeadGoal::RankHigher, LeadBudgetRange::ThreeToSix, OfferKey::GbpAudit, 120],
    'rank-google + 6000-12000' => [LeadGoal::RankHigher, LeadBudgetRange::SixToTwelve, OfferKey::GrowthStrategy, 999],
    'rank-google + 12000-plus' => [LeadGoal::RankHigher, LeadBudgetRange::TwelvePlus, OfferKey::GrowthStrategy, 999],

    'grow-online + under-3000' => [LeadGoal::GrowBusiness, LeadBudgetRange::Under3000, OfferKey::LeadGenerationAudit, 299],
    'grow-online + 3000-6000' => [LeadGoal::GrowBusiness, LeadBudgetRange::ThreeToSix, OfferKey::WebsiteGrowthAudit, 499],
    'grow-online + 6000-12000' => [LeadGoal::GrowBusiness, LeadBudgetRange::SixToTwelve, OfferKey::GrowthStrategy, 999],
    'grow-online + 12000-plus' => [LeadGoal::GrowBusiness, LeadBudgetRange::TwelvePlus, OfferKey::GrowthStrategy, 999],

    'not-sure + under-3000' => [LeadGoal::NotSure, LeadBudgetRange::Under3000, OfferKey::GbpAudit, 120],
    'not-sure + 3000-6000' => [LeadGoal::NotSure, LeadBudgetRange::ThreeToSix, OfferKey::GrowthStrategy, 999],
    'not-sure + 6000-12000' => [LeadGoal::NotSure, LeadBudgetRange::SixToTwelve, OfferKey::GrowthStrategy, 999],
    'not-sure + 12000-plus' => [LeadGoal::NotSure, LeadBudgetRange::TwelvePlus, OfferKey::GrowthStrategy, 999],
]);

it('resolves the correct offer and price for every goal x budget combination', function (LeadGoal $goal, LeadBudgetRange $budget, OfferKey $expectedOffer, int $expectedPrice) {
    $recommendation = OfferRecommendationMatrix::for($goal, $budget);

    expect($recommendation->goal)->toBe($goal)
        ->and($recommendation->budget)->toBe($budget)
        ->and($recommendation->offerKey)->toBe($expectedOffer)
        ->and($recommendation->priceRupees())->toBe($expectedPrice)
        ->and($recommendation->recommendationName)->not->toBeEmpty()
        ->and($recommendation->headline)->not->toBeEmpty()
        ->and($recommendation->positioning)->not->toBeEmpty()
        ->and($recommendation->explanation)->not->toBeEmpty()
        ->and($recommendation->cta())->toContain('₹'.$expectedPrice);
})->with('recommendation_matrix');

it('covers all 16 cells with no gaps', function () {
    expect(OfferRecommendationMatrix::all())->toHaveCount(16);
});

it('never frames a low budget as insufficient in its explanation copy', function () {
    foreach (LeadGoal::cases() as $goal) {
        $recommendation = OfferRecommendationMatrix::for($goal, LeadBudgetRange::Under3000);

        expect(mb_strtolower($recommendation->explanation))->not->toContain('too low')
            ->and(mb_strtolower($recommendation->explanation))->not->toContain('too small');
    }
});

it('every offer price matches OfferKey::price() exactly — single source of truth', function (LeadGoal $goal, LeadBudgetRange $budget, OfferKey $expectedOffer) {
    $recommendation = OfferRecommendationMatrix::for($goal, $budget);

    expect($recommendation->priceRupees())->toBe($expectedOffer->price());
})->with('recommendation_matrix');
