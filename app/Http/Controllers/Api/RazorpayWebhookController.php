<?php

namespace App\Http\Controllers\Api;

use App\Jobs\RecordGatewayPaymentJob;
use App\Jobs\RecordOfferPurchase;
use App\Models\OfferPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController
{
    /**
     * Receive a webhook event from Razorpay. Auth: HMAC-SHA256 signature
     * verified upstream by VerifyRazorpayWebhookSignature — the body is
     * already authenticated by the time we read it, so payment.entity.amount
     * is trusted directly (no extra API call needed, unlike the portal's
     * synchronous verify path which re-fetches the order).
     *
     * Only payment.captured is acted on — order.paid/refund/etc. events are
     * acknowledged and ignored. Every order created by this app via
     * RazorpayClient::createOrder() carries either notes.invoice_id (an
     * invoice/quotation payment) or notes.offer_key (one of the 3 new entry
     * offers — see OfferCheckoutController) — Razorpay copies order notes
     * onto every payment made against that order, so the payment entity
     * always carries whichever one applies. This webhook is a backup path
     * for the offer flow, which already records the purchase synchronously
     * in verify(); RecordOfferPurchase is idempotent either way.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');

        if ($event !== 'payment.captured') {
            return response()->json(['status' => 'ignored', 'reason' => 'unhandled_event']);
        }

        $payment = $request->input('payload.payment.entity', []);
        $paymentId = $payment['id'] ?? null;
        $orderId = $payment['order_id'] ?? null;
        $amount = $payment['amount'] ?? null;
        $notes = is_array($payment['notes'] ?? null) ? $payment['notes'] : [];

        if (! $paymentId || ! $orderId || ! $amount) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_fields']);
        }

        if (filled($notes['invoice_id'] ?? null)) {
            RecordGatewayPaymentJob::dispatch((int) $notes['invoice_id'], (string) $orderId, (string) $paymentId, (int) $amount);

            return response()->json(['status' => 'ok']);
        }

        if (filled($notes['offer_key'] ?? null)) {
            $purchase = OfferPurchase::where('razorpay_order_id', $orderId)->first();

            if ($purchase !== null) {
                RecordOfferPurchase::dispatch($purchase->id, (string) $paymentId);
            }

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'ignored', 'reason' => 'no_matching_notes']);
    }
}
