<?php

namespace App\Http\Controllers;

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use App\Jobs\RecordOfferPurchase;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use App\Services\RazorpayClient;
use App\Support\RazorpaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-app Razorpay Orders + Checkout.js flow for the 3 new entry offers
 * (App\Enums\OfferKey — GbpAudit excluded, see that enum's docblock).
 * Mirrors QuotationAdvancePaymentController::order()/verify() almost
 * exactly: the price is ALWAYS resolved server-side from OfferKey, never
 * from the request, and verify() re-fetches the order from Razorpay
 * directly rather than trusting anything the browser reports back.
 */
class OfferCheckoutController extends Controller
{
    public function order(string $offerKey, Request $request, RazorpayClient $razorpay): JsonResponse
    {
        $offer = $this->resolveOffer($offerKey);

        if (! $razorpay->configured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $lead = $this->resolveLead($request);

        $order = $razorpay->createOrder(
            $offer->priceInPaise(),
            'offer-'.Str::uuid(),
            ['offer_key' => $offer->value, 'lead_id' => (string) ($lead->id ?? '')],
        );

        if ($order === null) {
            return response()->json(['message' => 'Could not start the payment. Please try again shortly.'], 502);
        }

        OfferPurchase::create([
            'offer_key' => $offer->value,
            'price_paise' => $offer->priceInPaise(),
            'status' => OfferPurchaseStatus::Pending,
            'razorpay_order_id' => $order['id'],
            'lead_id' => $lead?->id,
            'payer_name' => $lead?->name,
            'payer_phone' => $lead?->phone,
            'payer_email' => $lead?->email,
        ]);

        if ($lead !== null) {
            $lead->forceFill(['offer_clicked_at' => $lead->offer_clicked_at ?? now()])->saveQuietly();

            OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferCtaClicked, 'offer_key' => $offer->value, 'lead_id' => $lead->id]);
            OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::PaymentStarted, 'offer_key' => $offer->value, 'lead_id' => $lead->id]);
        }

        return response()->json([
            'order_id' => $order['id'],
            'amount' => $offer->priceInPaise(),
            'key_id' => config('services.razorpay.key_id'),
            'offer_name' => $offer->label(),
            'company_name' => config('company.name'),
            'contact_name' => $lead?->name,
            'contact_email' => $lead?->email,
            'contact_phone' => $lead?->phone,
        ]);
    }

    public function verify(string $offerKey, Request $request, RazorpayClient $razorpay): JsonResponse
    {
        $offer = $this->resolveOffer($offerKey);

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $secret = (string) config('services.razorpay.key_secret');

        $valid = RazorpaySignature::verifyPayment(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            $secret,
        );

        if (! $valid) {
            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        // Re-fetch the order from Razorpay directly — never trust a
        // client-supplied amount/offer for money.
        $order = $razorpay->fetchOrder($data['razorpay_order_id']);
        $purchase = OfferPurchase::where('razorpay_order_id', $data['razorpay_order_id'])->first();

        $belongsToOffer = $order !== null
            && $purchase !== null
            && $purchase->offer_key === $offer
            && (int) ($order['amount'] ?? 0) === $offer->priceInPaise();

        if (! $belongsToOffer) {
            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        RecordOfferPurchase::dispatch($purchase->id, $data['razorpay_payment_id']);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Best-effort funnel tracking only — the Checkout.js `payment.failed`
     * event is purely client-side, so there is nothing here to verify
     * against Razorpay directly and nothing sensitive to protect.
     */
    public function failed(string $offerKey, Request $request): JsonResponse
    {
        $offer = $this->resolveOffer($offerKey);
        $lead = $this->resolveLead($request);

        OfferFunnelEvent::create([
            'event_type' => OfferFunnelEventType::PaymentFailed,
            'offer_key' => $offer->value,
            'lead_id' => $lead?->id,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function resolveOffer(string $offerKey): OfferKey
    {
        $offer = OfferKey::tryFrom($offerKey);

        abort_if($offer === null || ! $offer->usesInAppCheckout(), 404);

        return $offer;
    }

    private function resolveLead(Request $request): ?Lead
    {
        $leadId = $request->query('lead') ?? $request->input('lead_id');

        return is_numeric($leadId) ? Lead::find((int) $leadId) : null;
    }
}
