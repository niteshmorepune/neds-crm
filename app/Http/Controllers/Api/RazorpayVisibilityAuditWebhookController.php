<?php

namespace App\Http\Controllers\Api;

use App\Jobs\RecordVisibilityAuditPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayVisibilityAuditWebhookController
{
    /**
     * Receive a payment.captured event from the Visibility Audit offer's
     * Razorpay Payment Pages (auth: VerifyRazorpayVisibilityAuditWebhookSignature
     * — a separate secret from the invoice-payment webhook). Only
     * payment.captured is acted on, same as RazorpayWebhookController.
     *
     * `notes.name` is a best-effort read of a Payment Page custom "Name"
     * field — UNVERIFIED against a real payment as of this writing (the
     * Payment Pages don't exist yet), since Razorpay's exact key naming for
     * a custom field depends on how the field is configured in the
     * Dashboard. contact/email are standard Razorpay Checkout fields
     * present on every payment entity regardless of product, so those are
     * trusted directly. Worth checking a real payload's `notes` shape the
     * first time an actual purchase comes through.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');

        if ($event !== 'payment.captured') {
            return response()->json(['status' => 'ignored', 'reason' => 'unhandled_event']);
        }

        $payment = $request->input('payload.payment.entity', []);
        $paymentId = $payment['id'] ?? null;
        $amount = $payment['amount'] ?? null;

        if (! $paymentId || ! $amount) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_fields']);
        }

        $notes = is_array($payment['notes'] ?? null) ? $payment['notes'] : [];

        RecordVisibilityAuditPurchase::dispatch(
            paymentId: (string) $paymentId,
            orderId: $payment['order_id'] ?? null,
            amountPaise: (int) $amount,
            phone: $payment['contact'] ?? null,
            email: $payment['email'] ?? null,
            name: $notes['name'] ?? $notes['Name'] ?? $notes['full_name'] ?? null,
        );

        return response()->json(['status' => 'ok']);
    }
}
