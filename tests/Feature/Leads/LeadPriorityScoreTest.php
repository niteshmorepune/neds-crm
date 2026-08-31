<?php

use App\Enums\LeadStatus;
use App\Models\Lead;

it('scores an overdue follow-up highest, regardless of AI score', function () {
    $overdue = Lead::factory()->create(['ai_score' => 10, 'next_follow_up_at' => now()->subHour(), 'status' => LeadStatus::New]);
    $hotButFine = Lead::factory()->create(['ai_score' => 95, 'next_follow_up_at' => now()->addDay(), 'status' => LeadStatus::New]);

    expect($overdue->priorityScore())->toBeGreaterThan($hotButFine->priorityScore());
});

it('marks a past next_follow_up_at as overdue only while the lead is still open', function () {
    $open = Lead::factory()->create(['next_follow_up_at' => now()->subHour(), 'status' => LeadStatus::New]);
    $converted = Lead::factory()->create(['next_follow_up_at' => now()->subHour(), 'status' => LeadStatus::Converted]);

    expect($open->isFollowUpOverdue())->toBeTrue()
        ->and($converted->isFollowUpOverdue())->toBeFalse();
});

it('marks a follow-up due today distinctly from overdue or future', function () {
    $dueToday = Lead::factory()->create(['next_follow_up_at' => now()->addHours(2), 'status' => LeadStatus::New]);
    $overdue = Lead::factory()->create(['next_follow_up_at' => now()->subHour(), 'status' => LeadStatus::New]);
    $future = Lead::factory()->create(['next_follow_up_at' => now()->addDays(3), 'status' => LeadStatus::New]);

    expect($dueToday->isFollowUpDueToday())->toBeTrue()
        ->and($overdue->isFollowUpDueToday())->toBeFalse()
        ->and($future->isFollowUpDueToday())->toBeFalse();
});

it('accrues priority for a New lead with no follow-up set, the longer it has sat untouched, capped at 8 points over 20 days', function () {
    $fresh = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()]);
    $tenDaysOld = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()->subDays(10)]);
    $fortyDaysOld = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()->subDays(40)]);

    expect($tenDaysOld->priorityScore())->toBeGreaterThan($fresh->priorityScore())
        ->and($fortyDaysOld->priorityScore())->toBe($fresh->priorityScore() + 8); // capped at 20 days
});

it('also accrues staleness priority for a Contacted lead with no follow-up set, same as New', function () {
    // Real gap, 2026-08-31: a Contacted lead with nothing scheduled had no
    // forcing function to resurface it, unlike New -- it could go cold
    // forever with zero urgency pressure.
    $fresh = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::Contacted, 'created_at' => now()]);
    $fortyDaysOld = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::Contacted, 'created_at' => now()->subDays(40)]);

    expect($fortyDaysOld->priorityScore())->toBe($fresh->priorityScore() + 8); // capped at 20 days
});

it('never lets the staleness nudge outweigh a meaningful AI score gap — a hot fresh lead always outranks a stale mediocre one', function () {
    // Real production case, 2026-08-31: six 3-week-old AI-45 leads (maxed
    // out at the OLD +30 staleness cap = 75) outranked two genuinely hot
    // AI-72 leads created hours earlier (barely any nudge yet, ~73-74) --
    // hot leads buried under stale mediocre ones, the opposite of what a
    // priority list should show. The staleness cap was shrunk specifically
    // so this can never happen again, at any staleness age.
    $staleMediocre = Lead::factory()->create(['ai_score' => 45, 'next_follow_up_at' => null, 'status' => LeadStatus::Contacted, 'created_at' => now()->subWeeks(3)]);
    $hotFresh = Lead::factory()->create(['ai_score' => 72, 'next_follow_up_at' => null, 'status' => LeadStatus::Contacted, 'created_at' => now()->subHours(12)]);

    expect($hotFresh->priorityScore())->toBeGreaterThan($staleMediocre->priorityScore());
});

it('guarantees strict tiers: overdue always beats due-today, which always beats every open lead, at any score combination', function () {
    $weakestOverdue = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => now()->subHour(), 'status' => LeadStatus::New]);
    $strongestDueToday = Lead::factory()->create(['ai_score' => 100, 'next_follow_up_at' => now()->addHour(), 'status' => LeadStatus::New]);
    $weakestDueToday = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => now()->addHour(), 'status' => LeadStatus::New]);
    $strongestOpen = Lead::factory()->create(['ai_score' => 100, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()->subDays(40)]);

    expect($weakestOverdue->priorityScore())->toBeGreaterThan($strongestDueToday->priorityScore())
        ->and($weakestDueToday->priorityScore())->toBeGreaterThan($strongestOpen->priorityScore());
});

it('does not accrue staleness priority once a follow-up date is set or the lead has progressed past Contacted', function () {
    $followUpSet = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => now()->addWeek(), 'status' => LeadStatus::New, 'created_at' => now()->subDays(20)]);
    $progressed = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::Qualified, 'created_at' => now()->subDays(20)]);

    expect($followUpSet->priorityScore())->toBe(0)
        ->and($progressed->priorityScore())->toBe(0);
});

it('sinks a closed lead 1000 points below its raw AI score, never just the raw score', function () {
    $lost = Lead::factory()->create(['ai_score' => 42, 'status' => LeadStatus::Lost]);

    expect($lost->priorityScore())->toBe(42 - 1000);
});

it('always ranks a closed lead below every open lead, regardless of AI score', function () {
    // Real production case, 2026-08-31: a Lost lead with AI 65 outranked
    // several open, actionable leads on the Priority sort purely because it
    // scored well before it died.
    $hotButLost = Lead::factory()->create(['ai_score' => 100, 'status' => LeadStatus::Lost]);
    $coldButOpen = Lead::factory()->create(['ai_score' => 0, 'status' => LeadStatus::New, 'next_follow_up_at' => null, 'created_at' => now()]);

    expect($coldButOpen->priorityScore())->toBeGreaterThan($hotButLost->priorityScore());
});
