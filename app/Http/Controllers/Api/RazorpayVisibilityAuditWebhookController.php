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
     * contact/email are standard Razorpay Checkout fields present on every
     * payment entity regardless of product, so those are trusted directly.
     * Name/GBP link/Website are custom Payment Page fields the merchant
     * configures in the Dashboard — their exact `notes` key depends on how
     * each field is labelled there, which is UNVERIFIED against a real
     * payment as of this writing (the Payment Pages don't exist yet).
     * findNoteValue() matches by keyword rather than an exact key to survive
     * Razorpay slugifying the label differently than expected — worth
     * confirming against a real payload's `notes` shape on the first
     * genuine purchase.
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
            name: $this->findNoteValue($notes, ['name']),
            gbpUrl: $this->findNoteValue($notes, ['google', 'gbp', 'maps']),
            websiteUrl: $this->findNoteValue($notes, ['website', 'site']),
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $notes
     * @param  list<string>  $keywords
     */
    private function findNoteValue(array $notes, array $keywords): ?string
    {
        foreach ($notes as $key => $value) {
            $key = mb_strtolower((string) $key);

            foreach ($keywords as $keyword) {
                if (str_contains($key, $keyword)) {
                    return $value !== null && $value !== '' ? (string) $value : null;
                }
            }
        }

        return null;
    }
}
