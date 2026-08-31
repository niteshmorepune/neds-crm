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

it('generates sequential, gap-free, unique domestic invoice numbers in the bare-format era (FY 2026-27)', function () {
    $date = Carbon::parse('2026-06-10');

    $numbers = collect(range(1, 5))->map(fn () => $this->gen->generate($date));

    expect($numbers->all())->toBe([
        '26/27-001',
        '26/27-002',
        '26/27-003',
        '26/27-004',
        '26/27-005',
    ])->and($numbers->unique()->count())->toBe(5);
});

it('generates sequential export invoice numbers independently of the domestic sequence, same FY', function () {
    $date = Carbon::parse('2026-06-10');

    $this->gen->generate($date); // domestic 001
    $this->gen->generate($date); // domestic 002
    $export1 = $this->gen->generate($date, isOverseas: true);
    $domestic3 = $this->gen->generate($date);
    $export2 = $this->gen->generate($date, isOverseas: true);

    expect($export1)->toBe('26/27-IN001')
        ->and($export2)->toBe('26/27-IN002')
        ->and($domestic3)->toBe('26/27-003');
});

it('switches to the NEDS/ prefixed format starting FY 2027-28, keeping FY 2026-27 on the bare format', function () {
    $fy2627Domestic = $this->gen->generate(Carbon::parse('2026-06-10'));
    $fy2627Export = $this->gen->generate(Carbon::parse('2026-06-10'), isOverseas: true);
    $fy2728Domestic = $this->gen->generate(Carbon::parse('2027-05-10'));
    $fy2728Export = $this->gen->generate(Carbon::parse('2027-05-10'), isOverseas: true);

    expect($fy2627Domestic)->toBe('26/27-001')
        ->and($fy2627Export)->toBe('26/27-IN001')
        ->and($fy2728Domestic)->toBe('NEDS/27-28/001')
        ->and($fy2728Export)->toBe('NEDS/27-28/IN001');
});

it('self-heals when the domestic counter has drifted behind a manually-logged Hitech invoice number', function () {
    // Simulates a manually-logged Hitech invoice (InvoiceController::store/
    // importStore), which assigns its number directly without advancing the
    // shared counter — leaving the counter stuck well behind the real max.
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => '26/27-050']);

    $next = $this->gen->generate(Carbon::parse('2026-06-10'));

    expect($next)->toBe('26/27-051');
});

it('self-heals the export counter independently, ignoring a higher domestic number in the same FY', function () {
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => '26/27-050']);
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => '26/27-IN010']);

    // InvoiceFactory's own default invoice_number calls the real generator
    // (against actual today's FY) before our override replaces the stored
    // value -- resetting both counters to 0 here isolates this test to
    // purely the max-scan self-heal, regardless of what today's real date is.
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27', 'sequence_type' => 'domestic'], ['last_number' => 0]);
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27', 'sequence_type' => 'export'], ['last_number' => 0]);

    $nextExport = $this->gen->generate(Carbon::parse('2026-06-10'), isOverseas: true);
    $nextDomestic = $this->gen->generate(Carbon::parse('2026-06-10'));

    expect($nextExport)->toBe('26/27-IN011')
        ->and($nextDomestic)->toBe('26/27-051');
});

it('self-heals past a used number even when its financial_year column disagrees with the number itself', function () {
    // Reproduces a real production incident (originally against the old
    // NEDS/{fy}/{seq} format): a manually-logged, back-dated invoice
    // (InvoiceController::store) carries a 26/27-... number (typed by
    // staff) but issue_date in an earlier year, so financial_year is
    // independently computed as 2025-26 — desynced from the number's own
    // embedded fy. The self-heal filters by the number STRING itself, not
    // the financial_year column, so it can't be fooled by that mismatch.
    Invoice::factory()->create(['financial_year' => '2025-26', 'invoice_number' => '26/27-050']);

    $next = $this->gen->generate(Carbon::parse('2026-06-10'));

    expect($next)->toBe('26/27-051');
});

it('persists the self-healed counter as the real value, not just a relative bump off the stale one', function () {
    // Eloquent's increment() issues "column = column + amount" at the DB
    // level, ignoring any in-memory assignment made beforehand — so a naive
    // "$sequence->last_number = $maxUsed; $sequence->increment(...)" looks
    // right in the returned string but silently leaves the persisted
    // last_number lagging behind reality on every call.
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => '26/27-050']);
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27', 'sequence_type' => 'domestic'], ['last_number' => 3]);

    $this->gen->generate(Carbon::parse('2026-06-10'));

    expect(InvoiceNumberSequence::where('financial_year', '2026-27')->where('sequence_type', 'domestic')->first()->last_number)->toBe(51);
});

it('peek/peekNumber preview the next number without consuming or persisting it', function () {
    $date = Carbon::parse('2026-06-10');

    $preview1 = $this->gen->peek($date);
    $preview2 = $this->gen->peek($date);
    $previewNumber = $this->gen->peekNumber($date);
    $actual = $this->gen->generate($date);

    expect($preview1)->toBe('26/27-001')
        ->and($preview2)->toBe('26/27-001') // unchanged — peek never persists
        ->and($previewNumber)->toBe(1)
        ->and($actual)->toBe('26/27-001'); // matches what was previewed
});

it('lets an admin catch the counter up to a manually-set next number, per FY and sequence type', function () {
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27', 'sequence_type' => 'domestic'], ['last_number' => 39]);
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27', 'sequence_type' => 'export'], ['last_number' => 21]);

    $domestic = $this->gen->generate(Carbon::parse('2026-06-10'));
    $export = $this->gen->generate(Carbon::parse('2026-06-10'), isOverseas: true);

    expect($domestic)->toBe('26/27-040')
        ->and($export)->toBe('26/27-IN022');
});

it('generates Non-GST invoice numbers as INV/{fy}/{seq}, with no bare-format era — starts immediately this FY', function () {
    $fy2627 = $this->gen->generate(Carbon::parse('2026-06-10'), isGstExempt: true);
    $fy2728 = $this->gen->generate(Carbon::parse('2027-05-10'), isGstExempt: true);

    expect($fy2627)->toBe('INV/26-27/001')
        ->and($fy2728)->toBe('INV/27-28/001'); // independent counter, not FY2627's continuation
});

it('keeps the Non-GST sequence independent of domestic/export in the same FY', function () {
    $this->gen->generate(Carbon::parse('2026-06-10')); // domestic 001
    $this->gen->generate(Carbon::parse('2026-06-10'), isOverseas: true); // export IN001
    $nonGst1 = $this->gen->generate(Carbon::parse('2026-06-10'), isGstExempt: true);
    $domestic2 = $this->gen->generate(Carbon::parse('2026-06-10'));
    $nonGst2 = $this->gen->generate(Carbon::parse('2026-06-10'), isGstExempt: true);

    expect($nonGst1)->toBe('INV/26-27/001')
        ->and($nonGst2)->toBe('INV/26-27/002')
        ->and($domestic2)->toBe('26/27-002');
});

it('sends an overseas + Non-GST-exempt client to the export sequence, not Non-GST — export wins the overlap', function () {
    $result = $this->gen->generate(Carbon::parse('2026-06-10'), isOverseas: true, isGstExempt: true);

    expect($result)->toBe('26/27-IN001');
});

it('self-heals the Non-GST counter from a manually-logged INV number', function () {
    Invoice::factory()->create(['financial_year' => '2026-27', 'invoice_number' => 'INV/26-27/050']);

    $next = $this->gen->generate(Carbon::parse('2026-06-10'), isGstExempt: true);

    expect($next)->toBe('INV/26-27/051');
});

it('lets an admin catch the Non-GST counter up to a manually-set next number', function () {
    InvoiceNumberSequence::updateOrCreate(['financial_year' => '2026-27', 'sequence_type' => 'non_gst'], ['last_number' => 5]);

    $next = $this->gen->generate(Carbon::parse('2026-06-10'), isGstExempt: true);

    expect($next)->toBe('INV/26-27/006');
});
