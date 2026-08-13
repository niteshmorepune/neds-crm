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

it('accrues priority for a New lead with no follow-up set, the longer it has sat untouched, capped at 10 days', function () {
    $fresh = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()]);
    $threeDaysOld = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()->subDays(3)]);
    $twentyDaysOld = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::New, 'created_at' => now()->subDays(20)]);

    expect($threeDaysOld->priorityScore())->toBeGreaterThan($fresh->priorityScore())
        ->and($twentyDaysOld->priorityScore())->toBe($fresh->priorityScore() + 30); // capped at 10 days * 3
});

it('does not accrue staleness priority once a follow-up date is set or the lead has progressed', function () {
    $followUpSet = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => now()->addWeek(), 'status' => LeadStatus::New, 'created_at' => now()->subDays(20)]);
    $progressed = Lead::factory()->create(['ai_score' => 0, 'next_follow_up_at' => null, 'status' => LeadStatus::Contacted, 'created_at' => now()->subDays(20)]);

    expect($followUpSet->priorityScore())->toBe(0)
        ->and($progressed->priorityScore())->toBe(0);
});

it('falls back to the raw AI score for a closed lead', function () {
    $lost = Lead::factory()->create(['ai_score' => 42, 'status' => LeadStatus::Lost]);

    expect($lost->priorityScore())->toBe(42);
});
