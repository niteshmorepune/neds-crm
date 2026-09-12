<?php

namespace App\Http\Controllers;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Jobs\RecordVisibilityAuditPurchase;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use App\Services\RazorpayClient;
use App\Support\Ai;
use App\Support\RazorpaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-app Razorpay Orders + Checkout.js flow for the GBP Visibility Audit
 * offer (/offers/visibility-audit) — mirrors OfferCheckoutController's own
 * order()/verify() pattern almost exactly, but for the ₹120 GBP tier
 * specifically. Reverses the Milestone 12 decision to keep this page on its
 * own external Razorpay Payment Page; see the 2026-09-12 "GBP in-app
 * checkout" decisions log entry for why. Website/Website+GBP ("Both")
 * tiers are deliberately NOT covered here — they aren't linked from any
 * live page today and keep using the existing, untouched Payment Page +
 * RazorpayVisibilityAuditWebhookController mechanism unchanged.
 *
 * Unlike OfferCheckoutController, there is no "pending" purchase row
 * created at order() time — VisibilityAuditPurchase has never had a
 * pending/paid status concept (every row IS a completed payment, see that
 * model's own docblock), so the order's own Razorpay `notes` carry
 * everything verify() needs to know (tier, the submitted gbp_url, and the
 * matched lead id) rather than a placeholder DB row.
 */
class VisibilityAuditCheckoutController extends Controller
{
    private const AMOUNT_PAISE = 12000; // ₹120 — server-side by construction, never trusts the client.

    public function order(Request $request, RazorpayClient $razorpay): JsonResponse
    {
        if (! $razorpay->configured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $data = $request->validate([
            'gbp_url' => ['required', 'string', 'max:500'],
        ]);

        $lead = $this->resolveLead($request);

        $order = $razorpay->createOrder(
            self::AMOUNT_PAISE,
            'va-'.Str::uuid(),
            [
                'tier' => VisibilityAuditTier::Gbp->value,
                'gbp_url' => $data['gbp_url'],
                'lead_id' => (string) ($lead->id ?? ''),
            ],
        );

        if ($order === null) {
            return response()->json(['message' => 'Could not start the payment. Please try again shortly.'], 502);
        }

        // Same "reached checkout" tracking role the old external-redirect
        // checkout() action used to perform for GBP — now logged the moment
        // a real order is created instead of at an away-redirect.
        VisibilityAuditFunnelEvent::create([
            'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
            'tier' => VisibilityAuditTier::Gbp,
            'lead_id' => $lead?->id,
        ]);

        if ($lead !== null && Ai::enabled()) {
            ScoreLead::dispatch($lead->id);
        }

        return response()->json([
            'order_id' => $order['id'],
            'amount' => self::AMOUNT_PAISE,
            'key_id' => config('services.razorpay.key_id'),
            'offer_name' => VisibilityAuditTier::Gbp->label(),
            'company_name' => config('company.name'),
            'contact_name' => $lead?->name,
            'contact_email' => $lead?->email,
            'contact_phone' => $lead?->phone,
        ]);
    }

    public function verify(Request $request, RazorpayClient $razorpay): JsonResponse
    {
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
        // client-supplied amount/tier/gbp_url for money or downstream data.
        $order = $razorpay->fetchOrder($data['razorpay_order_id']);

        $notes = is_array($order['notes'] ?? null) ? $order['notes'] : [];
        $belongsToThisOffer = $order !== null
            && (int) ($order['amount'] ?? 0) === self::AMOUNT_PAISE
            && ($notes['tier'] ?? null) === VisibilityAuditTier::Gbp->value;

        if (! $belongsToThisOffer) {
            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        $gbpUrl = $notes['gbp_url'] ?? null;
        $leadId = filled($notes['lead_id'] ?? null) ? (int) $notes['lead_id'] : null;
        $lead = $leadId !== null ? Lead::find($leadId) : null;

        // The lead's own contact details take priority when we have them;
        // otherwise fall back to whatever the payer typed directly into
        // Razorpay's own Checkout.js modal (no prefill shown for an
        // unmatched/anonymous visitor, so Razorpay collects it itself) —
        // without this, RecordVisibilityAuditPurchase's own
        // Lead::findOpenByPhone() matching would never have a phone number
        // to work with for that visitor.
        $payment = $lead === null || blank($lead->phone) || blank($lead->email)
            ? $razorpay->fetchPayment($data['razorpay_payment_id'])
            : null;

        RecordVisibilityAuditPurchase::dispatch(
            paymentId: $data['razorpay_payment_id'],
            orderId: $data['razorpay_order_id'],
            amountPaise: self::AMOUNT_PAISE,
            phone: $lead?->phone ?? ($payment['contact'] ?? null),
            email: $lead?->email ?? ($payment['email'] ?? null),
            name: $lead?->name,
            gbpUrl: $gbpUrl,
            websiteUrl: null,
        );

        return response()->json(['status' => 'ok']);
    }

    private function resolveLead(Request $request): ?Lead
    {
        $leadId = $request->query('lead') ?? $request->input('lead_id');

        return is_numeric($leadId) ? Lead::find((int) $leadId) : null;
    }
}
