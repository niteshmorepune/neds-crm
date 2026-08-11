<?php

namespace App\Http\Controllers\Api;

use App\Jobs\RecordGatewayPaymentJob;
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
     * acknowledged and ignored. The order was created with notes.invoice_id
     * (RazorpayClient::createOrder), and Razorpay copies order notes onto
     * every payment made against that order, so the payment entity carries
     * it too.
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
        $invoiceId = $payment['notes']['invoice_id'] ?? null;

        if (! $paymentId || ! $orderId || ! $amount || ! $invoiceId) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_fields']);
        }

        RecordGatewayPaymentJob::dispatch((int) $invoiceId, (string) $orderId, (string) $paymentId, (int) $amount);

        return response()->json(['status' => 'ok']);
    }
}
