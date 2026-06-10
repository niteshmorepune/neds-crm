<?php

namespace App\Observers;

use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Support\Ai;

/**
 * Queues AI lead scoring whenever a lead is created, or updated in a way that
 * could change its score. Covers every creation path (back-office form, public
 * lead-capture API, future Livewire) because it hooks the model, not a
 * controller. Gated by Ai::enabled() so it is a true no-op when AI is off.
 */
class LeadObserver
{
    /** Fields that materially affect the score; an update touching none is ignored. */
    private const SCORING_FIELDS = ['name', 'company', 'email', 'phone', 'source', 'service_id', 'estimated_value'];

    public function created(Lead $lead): void
    {
        $this->queueScore($lead);
    }

    public function updated(Lead $lead): void
    {
        // The job writes ai_* columns via saveQuietly() (no event), so they never
        // reach here — only genuine field edits trigger a re-score.
        if ($lead->wasChanged(self::SCORING_FIELDS)) {
            $this->queueScore($lead);
        }
    }

    private function queueScore(Lead $lead): void
    {
        if (Ai::enabled()) {
            ScoreLead::dispatch($lead->id);
        }
    }
}
