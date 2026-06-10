<?php

use App\Rules\Gstin;
use Illuminate\Support\Facades\Validator;

function gstinPasses(string $value): bool
{
    return Validator::make(['gstin' => $value], ['gstin' => [new Gstin]])->passes();
}

it('accepts a valid GSTIN', function () {
    expect(gstinPasses('27ABCDE1234F1Z5'))->toBeTrue();
});

it('rejects invalid GSTINs', function (string $value) {
    expect(gstinPasses($value))->toBeFalse();
})->with([
    'too short' => '27ABCDE1234F1Z',
    'too long' => '27ABCDE1234F1Z55',
    'lowercase letters' => '27abcde1234f1z5',
    'missing Z' => '27ABCDE1234F1X5',
    'non-numeric state' => 'XYABCDE1234F1Z5',
    'all digits' => '111111111111111',
]);
