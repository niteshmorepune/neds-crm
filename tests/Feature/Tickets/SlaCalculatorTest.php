<?php

use App\Services\SlaCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->sla = new SlaCalculator;
    // Default config: Mon–Sat, 10:00–19:00 (9 business hours/day).
    $this->monday = Carbon::parse('2026-06-08')->startOfWeek()->setTime(11, 0); // Monday 11:00
});

it('resolves an urgent ticket (4h) within the same working day', function () {
    $due = $this->sla->dueAt($this->monday, 4);
    expect($due->format('D H:i'))->toBe('Mon 15:00');
});

it('lands a high ticket (8h) exactly at end of day', function () {
    $due = $this->sla->dueAt($this->monday, 8);
    expect($due->format('D H:i'))->toBe('Mon 19:00');
});

it('rolls a normal ticket (24h) across multiple working days', function () {
    // Mon 11:00→19:00 = 8h; Tue 10:00→19:00 = 9h (17 total); Wed 10:00 + 7h = 17:00.
    $due = $this->sla->dueAt($this->monday, 24);
    expect($due->format('D H:i'))->toBe('Wed 17:00');
});

it('skips Sundays (non-working) when rolling over the weekend', function () {
    $saturday = Carbon::parse('2026-06-08')->startOfWeek()->addDays(5)->setTime(18, 0); // Sat 18:00
    // Sat 18:00→19:00 = 1h; Sun skipped; Mon 10:00 + 3h = 13:00.
    $due = $this->sla->dueAt($saturday, 4);
    expect($due->format('D H:i'))->toBe('Mon 13:00');
});
