<?php

namespace App\Support;

/**
 * Razorpay's two documented HMAC-SHA256 verification formulas. Pure
 * functions — no HTTP, no config lookups — so both are trivial to unit test
 * without mocking anything.
 */
class RazorpaySignature
{
    /**
     * Checkout success-callback verification: proves the browser's
     * order_id/payment_id/signature triple was genuinely issued by Razorpay
     * for this exact order (documented formula: HMAC of "order_id|payment_id").
     */
    public static function verifyPayment(string $orderId, string $paymentId, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Webhook verification: HMAC over the raw request body, header
     * X-Razorpay-Signature (plain hex, no "sha256=" prefix — unlike Meta's
     * webhook header format).
     */
    public static function verifyWebhook(string $body, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }
}
