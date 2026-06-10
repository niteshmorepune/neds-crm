<?php

use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->gen = new InvoiceNumberGenerator;
});

it('computes the April–March financial year', function (string $date, string $fy) {
    expect($this->gen->financialYear(Carbon::parse($date)))->toBe($fy);
})->with([
    ['2026-06-10', '2026-27'],
    ['2026-04-01', '2026-27'],
    ['2026-03-31', '2025-26'],
    ['2026-02-15', '2025-26'],
    ['2027-01-01', '2026-27'],
]);

it('generates sequential, gap-free, unique invoice numbers', function () {
    $date = Carbon::parse('2026-06-10');

    $numbers = collect(range(1, 5))->map(fn () => $this->gen->generate($date));

    expect($numbers->all())->toBe([
        'NEDS/2026-27/0001',
        'NEDS/2026-27/0002',
        'NEDS/2026-27/0003',
        'NEDS/2026-27/0004',
        'NEDS/2026-27/0005',
    ])->and($numbers->unique()->count())->toBe(5);
});

it('keeps separate sequences per financial year', function () {
    $fy1 = $this->gen->generate(Carbon::parse('2026-06-10')); // 2026-27
    $fy2 = $this->gen->generate(Carbon::parse('2027-05-10')); // 2027-28

    expect($fy1)->toBe('NEDS/2026-27/0001')
        ->and($fy2)->toBe('NEDS/2027-28/0001');
});
