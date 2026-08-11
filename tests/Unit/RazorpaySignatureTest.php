<?php

use App\Support\RazorpaySignature;

it('verifies a correctly signed payment', function () {
    $secret = 'test-key-secret';
    $orderId = 'order_ABC123';
    $paymentId = 'pay_XYZ789';
    $signature = hash_hmac('sha256', "{$orderId}|{$paymentId}", $secret);

    expect(RazorpaySignature::verifyPayment($orderId, $paymentId, $signature, $secret))->toBeTrue();
});

it('rejects a payment signature signed with the wrong secret', function () {
    $orderId = 'order_ABC123';
    $paymentId = 'pay_XYZ789';
    $signature = hash_hmac('sha256', "{$orderId}|{$paymentId}", 'wrong-secret');

    expect(RazorpaySignature::verifyPayment($orderId, $paymentId, $signature, 'test-key-secret'))->toBeFalse();
});

it('rejects a payment signature for a different order/payment pair', function () {
    $secret = 'test-key-secret';
    $signature = hash_hmac('sha256', 'order_ABC123|pay_XYZ789', $secret);

    expect(RazorpaySignature::verifyPayment('order_OTHER', 'pay_XYZ789', $signature, $secret))->toBeFalse();
});

it('rejects an empty payment signature or secret', function () {
    expect(RazorpaySignature::verifyPayment('order_1', 'pay_1', '', 'secret'))->toBeFalse()
        ->and(RazorpaySignature::verifyPayment('order_1', 'pay_1', 'sig', ''))->toBeFalse();
});

it('verifies a correctly signed webhook body', function () {
    $secret = 'test-webhook-secret';
    $body = json_encode(['event' => 'payment.captured']);
    $signature = hash_hmac('sha256', $body, $secret);

    expect(RazorpaySignature::verifyWebhook($body, $signature, $secret))->toBeTrue();
});

it('rejects a webhook body signed with the wrong secret', function () {
    $body = json_encode(['event' => 'payment.captured']);
    $signature = hash_hmac('sha256', $body, 'wrong-secret');

    expect(RazorpaySignature::verifyWebhook($body, $signature, 'test-webhook-secret'))->toBeFalse();
});

it('rejects a tampered webhook body', function () {
    $secret = 'test-webhook-secret';
    $signature = hash_hmac('sha256', json_encode(['event' => 'payment.captured']), $secret);

    expect(RazorpaySignature::verifyWebhook(json_encode(['event' => 'payment.refunded']), $signature, $secret))->toBeFalse();
});
