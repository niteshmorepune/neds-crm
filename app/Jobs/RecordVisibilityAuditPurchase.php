<?php

namespace App\Jobs;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Records a completed Razorpay Payment Page payment from the Visibility
 * Audit offer (/offers/visibility-audit) and matches it to the Lead the
 * payer almost certainly already has (they filled Meta's native lead form
 * before ever reaching this page) — same Lead::findOpenByPhone() dedup
 * mechanism ImportMetaLead already uses for its own duplicate-channel case.
 *
 * Deliberately does NOT auto-create a Deal — Deal.value drives the Sales
 * Incentive calculator and pipeline metrics, and every other Lead in this
 * app becomes a Deal only via the existing manual ConvertLead action. A
 * paid-audit note gives Sales the real signal without a stray placeholder
 * Deal to forget about.
 *
 * Idempotent on visibility_audit_purchases.razorpay_payment_id (unique
 * column) — same "check, then let the unique constraint win a race" pattern
 * as RazorpayPaymentRecorder.
 *
 * Also the one place a LeadAssignmentRule with va_paid=true gets checked —
 * LeadObserver::autoAssign() can't see this signal, since a lead's VA
 * funnel state isn't known yet at the moment a bare Lead::create() fires it.
 * Only ever assigns an unowned lead; never reassigns one that already has
 * an owner (see assignViaVaPaidRuleIfUnowned()).
 *
 * Also stamps time_to_payment_minutes ("Lead to Won" Phase 3, Task 3 --
 * capture only) -- the delta between the matched lead's first tracked
 * landing-page view and this purchase. Measurement only: nothing reads
 * this value back into ScoreLead's prompt, LeadAssignmentRule, or any
 * other scoring/routing decision; it exists purely so there's a real
 * time-to-payment trend to evaluate once enough purchases have one.
 */
class RecordVisibilityAuditPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $paymentId,
        public ?string $orderId,
        public int $amountPaise,
        public ?string $phone,
        public ?string $email,
        public ?string $name,
        public ?string $gbpUrl = null,
        public ?string $websiteUrl = null,
    ) {}

    public function handle(): void
    {
        if (VisibilityAuditPurchase::where('razorpay_payment_id', $this->paymentId)->exists()) {
            return;
        }

        $tier = VisibilityAuditTier::fromAmountPaise($this->amountPaise);

        try {
            $purchase = VisibilityAuditPurchase::create([
                'tier' => $tier?->value,
                'amount_paise' => $this->amountPaise,
                'razorpay_payment_id' => $this->paymentId,
                'razorpay_order_id' => $this->orderId,
                'payer_name' => $this->name,
                'payer_phone' => $this->phone,
                'payer_email' => $this->email,
                'gbp_url' => $this->gbpUrl,
                'website_url' => $this->websiteUrl,
            ]);
        } catch (QueryException $e) {
            // Duplicate webhook delivery raced our existence check above.
            return;
        }

        // No-ops until the wadesk.in payment-confirmation template is
        // configured; see the job's own docblock.
        SendVisibilityAuditPaymentConfirmationJob::dispatch($purchase->id);

        // Unmistakable staff-facing alert — every purchase, whether it ends
        // up matching an existing Lead, creating a new one, or finding no
        // phone at all. See the job's own docblock for the real incident
        // this closes (2026-08-27: a payment with no staff-facing signal at
        // all got missed and its auto-created Lead deleted as noise).
        SendVisibilityAuditPurchaseAlertJob::dispatch($purchase->id);

        // Email half of the same thank-you — no-ops when the purchase has
        // no payer_email; see the job's own docblock.
        SendVisibilityAuditPaymentReceiptEmailJob::dispatch($purchase->id);

        if ($tier === null) {
            Log::warning('Visibility Audit payment amount matched no known tier', [
                'payment_id' => $this->paymentId,
                'amount_paise' => $this->amountPaise,
            ]);
        }

        if (blank($this->phone)) {
            Log::warning('Visibility Audit payment has no phone number to match a Lead', [
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        $lead = Lead::findOpenByPhone($this->phone);

        $lead = $lead !== null
            ? $this->attachToExistingLead($lead, $tier)
            : $this->createLead($tier);

        // A pre-existing lead that was never assigned (e.g. it predates any
        // active Sales user) gets one more chance at the VA-Paid rule here.
        // createLead() below already resolves it for the brand-new-lead case
        // up front, before round-robin ever runs -- this is a no-op for that
        // branch once it has already found an owner.
        $this->assignViaVaPaidRuleIfUnowned($lead);

        $purchase->update([
            'lead_id' => $lead->id,
            'time_to_payment_minutes' => $this->timeToPaymentMinutes($lead, $purchase),
        ]);

        // A completed payment is the strongest buying-intent signal this
        // lead will ever produce -- re-score so ai_score/priorityScore pick
        // it up immediately, same "real engagement re-scores" precedent as
        // RecordNotes::addNote()/CallLogController::store(). Covers the
        // new-lead branch too: Lead::create() above already triggered an
        // initial score via LeadObserver, but that ran before the payment
        // note (createLead()/attachToExistingLead() both add one) existed.
        if (Ai::enabled()) {
            ScoreLead::dispatch($lead->id);
        }
    }

    /**
     * "Lead to Won" Phase 3, Task 3 -- capture only, never read by ScoreLead
     * or any other scoring/routing decision (see this job's own updated
     * docblock, and the migration's docblock for the going-forward-only
     * choice). Null when the matched lead has no tracked landing-page view
     * at all -- a purchase whose payer never passed through the tracked
     * /offers/visibility-audit/enter redirect, or one predating that
     * tracking (2026-08-15).
     */
    private function timeToPaymentMinutes(Lead $lead, VisibilityAuditPurchase $purchase): ?int
    {
        $firstLandingView = VisibilityAuditFunnelEvent::query()
            ->where('lead_id', $lead->id)
            ->where('event_type', VisibilityAuditFunnelEventType::LandingViewed)
            ->min('created_at');

        if ($firstLandingView === null) {
            return null;
        }

        return Carbon::parse($firstLandingView)->diffInMinutes($purchase->created_at);
    }

    private function attachToExistingLead(Lead $lead, ?VisibilityAuditTier $tier): Lead
    {
        $serviceId = $this->serviceIdForTier($tier);

        if ($serviceId !== null && $lead->service_id === null) {
            $lead->update(['service_id' => $serviceId]);
        }

        $lead->notes()->create(['user_id' => null, 'body' => $this->noteBody($tier)]);

        return $lead;
    }

    private function createLead(?VisibilityAuditTier $tier): Lead
    {
        // Resolved BEFORE create() so a matching VA-Paid rule wins outright --
        // if owner_id already arrives non-null, LeadObserver::autoAssign()
        // sees it's set and never runs its own round-robin fallback, so
        // there is no reassignment to guard against for this branch.
        $ownerId = $this->resolveVaPaidAssignee()?->id;

        $lead = Lead::create([
            'name' => $this->name ?: 'Visibility Audit Customer',
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => LeadSource::Other->value,
            'service_id' => $this->serviceIdForTier($tier),
            'status' => LeadStatus::New->value,
            'owner_id' => $ownerId,
            'utm_source' => 'visibility-audit-offer',
        ]);

        $lead->notes()->create(['user_id' => null, 'body' => $this->noteBody($tier)]);

        return $lead;
    }

    /**
     * Only ever assigns a lead that is currently unowned -- an owned lead is
     * left alone, no matter how it got its owner. Reassignment in this app
     * is always a visible, logged, human action (ReassignLead), never a
     * silent side effect of a payment webhook.
     */
    private function assignViaVaPaidRuleIfUnowned(Lead $lead): void
    {
        if ($lead->owner_id !== null) {
            return;
        }

        $assignee = $this->resolveVaPaidAssignee();

        if ($assignee !== null) {
            $lead->update(['owner_id' => $assignee->id]);
        }
    }

    private function resolveVaPaidAssignee(): ?User
    {
        return LeadAssignmentRule::active()->where('va_paid', true)->with('assignedUser')->first()?->eligibleAssignee();
    }

    private function noteBody(?VisibilityAuditTier $tier): string
    {
        $amount = number_format($this->amountPaise / 100);
        $label = $tier?->label() ?? 'a Visibility Audit';

        $body = "Paid ₹{$amount} for {$label} via the Visibility Audit offer page (Razorpay payment {$this->paymentId}).";

        $wantsGbp = in_array($tier, [VisibilityAuditTier::Gbp, VisibilityAuditTier::Both], true);
        $wantsWebsite = in_array($tier, [VisibilityAuditTier::Website, VisibilityAuditTier::Both], true);

        if ($wantsGbp) {
            $body .= "\nGBP profile: ".($this->gbpUrl ?: 'NOT PROVIDED — follow up with the customer to get this before starting the audit.');
        }

        if ($wantsWebsite) {
            $body .= "\nWebsite: ".($this->websiteUrl ?: 'NOT PROVIDED — follow up with the customer to get this before starting the audit.');
        }

        return $body;
    }

    /**
     * Only backfills for a single-service tier — "Both" is intentionally
     * left unset rather than guessing which of the two services matters
     * more to this lead.
     */
    private function serviceIdForTier(?VisibilityAuditTier $tier): ?int
    {
        $name = match ($tier) {
            VisibilityAuditTier::Gbp => 'GMB',
            VisibilityAuditTier::Website => 'Website Design & Development',
            default => null,
        };

        return $name === null ? null : Service::where('name', $name)->value('id');
    }
}
