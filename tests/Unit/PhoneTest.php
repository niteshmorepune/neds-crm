<?php

use App\Support\Phone;

it('adds the 91 country code to a bare 10-digit number for WhatsApp', function (string $raw, string $expected) {
    expect(Phone::forWhatsapp($raw))->toBe($expected);
})->with([
    'bare 10 digits' => ['8529857994', '918529857994'],
    'formatted 10 digits' => ['85298 57994', '918529857994'],
    'leading trunk zero' => ['09146317832', '919146317832'],
    'already has 91' => ['918529857994', '918529857994'],
    'plus and spaces' => ['+91 98765 43210', '919876543210'],
    'foreign number untouched' => ['447950809222', '447950809222'],
    'US number untouched' => ['16315551181', '16315551181'],
]);
