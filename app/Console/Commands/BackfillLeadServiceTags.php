<?php

namespace App\Console\Commands;

use App\Actions\GenerateLeadRecommendation;
use App\Enums\OfferKey;
use App\Models\Lead;
use Illuminate\Console\Command;

/**
 * One-off correction: staff had been defaulting nearly every Meta lead's
 * service_id to GMB by habit, regardless of what its real goal+budget
 * answers actually resolved to — see the 2026-09-12 "service tag
 * auto-derive" decisions log entry. GenerateLeadRecommendation::handle()
 * now keeps this correct going forward for any NEW/CHANGED recommendation;
 * this command corrects every Meta lead that already has a resolved
 * recommendation_offer_key, overwriting service_id to match
 * GenerateLeadRecommendation::serviceIdForOffer() even when it currently
 * disagrees (e.g. a habitual GMB tag on a lead the matrix actually
 * recommends Website Growth Audit for).
 */
class BackfillLeadServiceTags extends Command
{
    protected $signature = 'app:backfill-lead-service-tags {--dry-run : Show what would change without saving}';

    protected $description = 'Correct Lead.service_id to match each Meta lead\'s resolved recommendation_offer_key (one-off correction for habitual manual mistagging).';

    public function handle(GenerateLeadRecommendation $recommendation): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $leads = Lead::whereNotNull('recommendation_offer_key')->get(['id', 'name', 'service_id', 'recommendation_offer_key']);

        $changed = 0;

        foreach ($leads as $lead) {
            $offerKey = OfferKey::tryFrom($lead->recommendation_offer_key);

            if ($offerKey === null) {
                continue;
            }

            $correctServiceId = $recommendation->serviceIdForOffer($offerKey);

            if ($correctServiceId === null || $correctServiceId === $lead->service_id) {
                continue;
            }

            $this->line("Lead #{$lead->id} ({$lead->name}): service_id {$lead->service_id} -> {$correctServiceId} (offer: {$offerKey->value})");

            if (! $dryRun) {
                $lead->forceFill(['service_id' => $correctServiceId])->saveQuietly();
            }

            $changed++;
        }

        $this->info(($dryRun ? '[dry run] Would correct ' : 'Corrected ')."{$changed} of {$leads->count()} lead(s) with a resolved recommendation.");

        return self::SUCCESS;
    }
}
