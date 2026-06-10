<?php

use App\Services\GstCalculator;

beforeEach(function () {
    $this->gst = new GstCalculator;
});

it('splits CGST and SGST for intra-state supply (Maharashtra)', function () {
    $r = $this->gst->calculate([['quantity' => 1, 'rate' => 100000, 'gst_rate' => 18]], 0, '27');

    expect($r['is_intra_state'])->toBeTrue()
        ->and($r['subtotal'])->toBe(100000)
        ->and($r['taxable_total'])->toBe(100000)
        ->and($r['cgst_total'])->toBe(9000)
        ->and($r['sgst_total'])->toBe(9000)
        ->and($r['igst_total'])->toBe(0)
        ->and($r['total'])->toBe(118000);
});

it('charges IGST for inter-state supply', function () {
    $r = $this->gst->calculate([['quantity' => 1, 'rate' => 100000, 'gst_rate' => 18]], 0, '29');

    expect($r['is_intra_state'])->toBeFalse()
        ->and($r['igst_total'])->toBe(18000)
        ->and($r['cgst_total'])->toBe(0)
        ->and($r['sgst_total'])->toBe(0)
        ->and($r['total'])->toBe(118000);
});

it('puts the odd paise on SGST when tax is not even', function () {
    // 100005 * 18% = 18000.9 -> 18001 (odd); cgst 9000, sgst 9001.
    $r = $this->gst->calculate([['quantity' => 1, 'rate' => 100005, 'gst_rate' => 18]], 0, '27');

    expect($r['cgst_total'])->toBe(9000)
        ->and($r['sgst_total'])->toBe(9001);
});

it('rounds the final total to the nearest rupee with a round-off', function () {
    // taxable 100033, tax round(18005.94)=18006, preRound 118039 -> 118000, round_off -39.
    $r = $this->gst->calculate([['quantity' => 1, 'rate' => 100033, 'gst_rate' => 18]], 0, '27');

    expect($r['round_off'])->toBe(-39)
        ->and($r['total'])->toBe(118000)
        ->and($r['total'] % 100)->toBe(0);
});

it('distributes a document discount across lines proportionally', function () {
    $r = $this->gst->calculate([
        ['quantity' => 1, 'rate' => 100000, 'gst_rate' => 18],
        ['quantity' => 1, 'rate' => 200000, 'gst_rate' => 18],
    ], 30000, '27');

    expect($r['subtotal'])->toBe(300000)
        ->and($r['discount'])->toBe(30000)
        ->and($r['taxable_total'])->toBe(270000)
        ->and($r['lines'][0]['discount'])->toBe(10000)
        ->and($r['lines'][1]['discount'])->toBe(20000);
});

it('handles mixed GST rates per line', function () {
    $r = $this->gst->calculate([
        ['quantity' => 1, 'rate' => 100000, 'gst_rate' => 18],
        ['quantity' => 1, 'rate' => 100000, 'gst_rate' => 5],
    ], 0, '27');

    // 18% -> 18000, 5% -> 5000, total tax 23000; cgst+sgst = 11500+11500.
    expect($r['cgst_total'])->toBe(11500)
        ->and($r['sgst_total'])->toBe(11500)
        ->and($r['total'])->toBe(223000);
});
