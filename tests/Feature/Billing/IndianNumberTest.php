<?php

use App\Support\IndianNumber;

it('converts paise to Indian-system words', function (int $paise, string $words) {
    expect(IndianNumber::toWords($paise))->toBe($words);
})->with([
    'zero' => [0, 'Rupees Zero Only'],
    'thousand' => [100000, 'Rupees One Thousand Only'],
    'one lakh' => [10000000, 'Rupees One Lakh Only'],
    'lakh + thousand' => [12500000, 'Rupees One Lakh Twenty Five Thousand Only'],
    'with paise' => [105050, 'Rupees One Thousand Fifty and Fifty Paise Only'],
    'one crore' => [1000000000, 'Rupees One Crore Only'],
    'complex' => [123456789, 'Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven and Eighty Nine Paise Only'],
]);
