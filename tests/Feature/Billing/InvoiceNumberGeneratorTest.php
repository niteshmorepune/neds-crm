<?php

use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
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

it('self-heals when the counter has drifted behind a manually-assigned invoice number', function () {
    // Simulates a manually-logged/CSV-imported invoice (InvoiceController::store/
    // importStore), which assigns its number directly without advancing the
    // shared counter — leaving the counter stuck well behind the real max.
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => 'NEDS/2026-27/0050']);

    $next = $this->gen->generate(Carbon::parse('2026-06-10'));

    expect($next)->toBe('NEDS/2026-27/0051');
});

it('self-heals past a used number even when its financial_year column disagrees with the number itself', function () {
    // Reproduces a real production incident: a manually-logged, back-dated
    // invoice (InvoiceController::store) carries a NEDS/2026-27/... number
    // (typed by staff) but issue_date in an earlier year, so financial_year
    // is independently computed as 2025-26 — desynced from the number's own
    // embedded fy. The old self-heal filtered maxUsed by the financial_year
    // *column*, so it never saw this row and kept re-proposing the same
    // already-taken number on every call (a permanent 500 until fixed).
    Invoice::factory()->create(['financial_year' => '2025-26', 'invoice_number' => 'NEDS/2026-27/0050']);

    $next = $this->gen->generate(Carbon::parse('2026-06-10'));

    expect($next)->toBe('NEDS/2026-27/0051');
});

it('persists the self-healed counter as the real value, not just a relative bump off the stale one', function () {
    // Eloquent's increment() issues "column = column + amount" at the DB
    // level, ignoring any in-memory assignment made beforehand — so a naive
    // "$sequence->last_number = $maxUsed; $sequence->increment(...)" looks
    // right in the returned string but silently leaves the persisted
    // last_number lagging behind reality on every call.
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => 'NEDS/2026-27/0050']);
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27'], ['last_number' => 3]);

    $this->gen->generate(Carbon::parse('2026-06-10'));

    expect(InvoiceNumberSequence::where('financial_year', '2026-27')->first()->last_number)->toBe(51);
});
