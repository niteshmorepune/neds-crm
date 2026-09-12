<?php

namespace App\Jobs;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\OfferFunnelEventType;
use App\Enums\OfferPurchaseStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use App\Models\User;
use App\Notifications\OfferPurchasedNotification;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Marks an OfferPurchase paid after a verified Razorpay payment
 * (OfferCheckoutController::verify()) and matches/creates the Lead, same
 * "the payer almost certainly already has a Lead — match by phone, only
 * create one if truly none exists" logic as RecordVisibilityAuditPurchase.
 * Idempotent on offer_purchases.razorpay_payment_id (unique column).
 */
class RecordOfferPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $purchaseId,
        public string $paymentId,
    ) {}

    public function handle(): void
    {
        $purchase = OfferPurchase::find($this->purchaseId);

        if ($purchase === null || $purchase->status === OfferPurchaseStatus::Paid) {
            return;
        }

        try {
            $purchase->update([
                'status' => OfferPurchaseStatus::Paid,
                'razorpay_payment_id' => $this->paymentId,
                'paid_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Duplicate webhook delivery raced the sync verify() call.
            return;
        }

        $lead = $purchase->lead ?? $this->matchOrCreateLead($purchase);

        if ($lead !== null) {
            if ($purchase->lead_id === null) {
                $purchase->update(['lead_id' => $lead->id]);
            }

            // Reflects the checkout-captured link onto the Lead's own
            // website_url field (same field the Goal-capture panel reads),
            // not just onto this purchase row — a deliberately fresh value
            // typed at checkout is worth overwriting a stale/blank one with.
            // A no-op update() when it's unchanged (e.g. it was prefilled
            // from the lead itself) fires no query/activity event.
            if ($purchase->website_url !== null) {
                $lead->update(['website_url' => $purchase->website_url]);
            }

            $amount = number_format($purchase->price_paise / 100);
            $lead->notes()->create([
                'user_id' => null,
                'body' => "Paid ₹{$amount} for {$purchase->offer_key->label()} via the {$purchase->offer_key->shortLabel()} offer page (Razorpay payment {$this->paymentId}).",
            ]);

            OfferFunnelEvent::create([
                'event_type' => OfferFunnelEventType::PaymentSucceeded,
                'offer_key' => $purchase->offer_key->value,
                'lead_id' => $lead->id,
            ]);

            if (Ai::enabled()) {
                ScoreLead::dispatch($lead->id);
            }
        } elseif (blank($purchase->payer_phone)) {
            Log::warning('Offer purchase has no phone number to match a Lead', ['purchase_id' => $purchase->id]);
        }

        $this->notifyStaff($purchase);
    }

    private function matchOrCreateLead(OfferPurchase $purchase): ?Lead
    {
        if (blank($purchase->payer_phone)) {
            return null;
        }

        $existing = Lead::findOpenByPhone($purchase->payer_phone);

        if ($existing !== null) {
            return $existing;
        }

        return Lead::create([
            'name' => $purchase->payer_name ?: 'Offer Purchase Customer',
            'email' => $purchase->payer_email,
            'phone' => $purchase->payer_phone,
            'source' => LeadSource::Other->value,
            'status' => LeadStatus::New->value,
            'utm_source' => 'offer-'.$purchase->offer_key->value,
        ]);
    }

    private function notifyStaff(OfferPurchase $purchase): void
    {
        $staff = User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        foreach ($staff as $user) {
            $user->notify(new OfferPurchasedNotification($purchase));
        }
    }
}
