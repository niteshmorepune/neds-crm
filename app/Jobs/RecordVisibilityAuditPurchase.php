<?php

namespace App\Jobs;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $purchase->update(['lead_id' => $lead->id]);
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
        $lead = Lead::create([
            'name' => $this->name ?: 'Visibility Audit Customer',
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => LeadSource::Other->value,
            'service_id' => $this->serviceIdForTier($tier),
            'status' => LeadStatus::New->value,
            'owner_id' => null,
            'utm_source' => 'visibility-audit-offer',
        ]);

        $lead->notes()->create(['user_id' => null, 'body' => $this->noteBody($tier)]);

        return $lead;
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
