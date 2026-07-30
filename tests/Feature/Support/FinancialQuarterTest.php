<?php

use App\Support\FinancialQuarter;
use Illuminate\Support\Carbon;

it('computes the financial year the same way InvoiceNumberGenerator does', function () {
    expect(FinancialQuarter::financialYear(Carbon::create(2026, 4, 1)))->toBe('2026-27')
        ->and(FinancialQuarter::financialYear(Carbon::create(2027, 3, 31)))->toBe('2026-27')
        ->and(FinancialQuarter::financialYear(Carbon::create(2026, 1, 1)))->toBe('2025-26');
});

it('buckets months into FY quarters correctly', function (int $month, int $expectedQuarter) {
    expect(FinancialQuarter::quarterOf(Carbon::create(2026, $month, 15)))->toBe($expectedQuarter);
})->with([
    'April (Q1 start)' => [4, 1],
    'June (Q1 end)' => [6, 1],
    'July (Q2 start)' => [7, 2],
    'September (Q2 end)' => [9, 2],
    'October (Q3 start)' => [10, 3],
    'December (Q3 end)' => [12, 3],
    'January (Q4 start)' => [1, 4],
    'March (Q4 end)' => [3, 4],
]);

it('gives the correct start/end instants for a quarter, including the Q4 year rollover', function () {
    [$start, $end] = FinancialQuarter::range('2026-27', 1);
    expect($start->toDateString())->toBe('2026-04-01')
        ->and($end->toDateString())->toBe('2026-06-30');

    [$start, $end] = FinancialQuarter::range('2026-27', 4);
    expect($start->toDateString())->toBe('2027-01-01')
        ->and($end->toDateString())->toBe('2027-03-31');
});

it('resolves the quarter that just ended, including across a financial-year boundary', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 15)); // early in FY2026-27 Q1
    expect(FinancialQuarter::previous())->toBe(['financial_year' => '2025-26', 'quarter' => 4]);

    Carbon::setTestNow(Carbon::create(2026, 8, 1)); // mid FY2026-27 Q2
    expect(FinancialQuarter::previous())->toBe(['financial_year' => '2026-27', 'quarter' => 1]);

    Carbon::setTestNow(); // reset
});
