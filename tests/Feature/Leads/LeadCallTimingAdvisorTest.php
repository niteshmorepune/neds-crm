<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\CallTimingMetrics;
use App\Services\LeadCallTimingAdvisor;
use Database\Seeders\MenuItemsSeeder;

/**
 * Builds a CallLog against a lead at a specific Asia/Kolkata hour, mirroring
 * CallTimingMetricsTest's own callAt() helper but scoped to a lead so both
 * files can coexist without a naming clash.
 */
function callLeadAt(Lead $lead, int $hour, int $daysAgo, CallOutcome $outcome = CallOutcome::Connected): CallLog
{
    return CallLog::factory()->create([
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => $outcome,
        'called_at' => Carbon\Carbon::now('Asia/Kolkata')->subDays($daysAgo)->setTime($hour, 0, 0)->utc(),
    ]);
}

/** Gives CallTimingMetrics::bestHours() a trustworthy 9 AM / 11 AM band (>= MIN_SAMPLE each). */
function seedGlobalBestHours(): void
{
    $rep = User::factory()->create();
    foreach ([9, 11] as $hour) {
        for ($i = 1; $i <= 15; $i++) {
            CallLog::factory()->create([
                'user_id' => $rep->id,
                'direction' => CallDirection::Outgoing,
                'outcome' => CallOutcome::Connected,
                'called_at' => Carbon\Carbon::now('Asia/Kolkata')->subDays($i)->setTime($hour, 0, 0)->utc(),
            ]);
        }
    }
}

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('falls back to the global best-hour band when the lead has no calls of its own', function () {
    seedGlobalBestHours();
    $lead = Lead::factory()->create();

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['own_attempt_count'])->toBe(0)
        ->and($advice['recommended_hours'])->toBe([9, 11])
        ->and($advice['recommended_label'])->toBe('9 AM, 11 AM')
        ->and($advice['basis_note'])->toContain('based on team-wide calling patterns');
});

it('lists a lead\'s own attempts, most recent first', function () {
    $lead = Lead::factory()->create();
    callLeadAt($lead, 9, daysAgo: 3, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 15, daysAgo: 1, outcome: CallOutcome::Busy);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['own_attempt_count'])->toBe(2)
        ->and($advice['own_attempts']->first()['hour'])->toBe(15)
        ->and($advice['own_attempts']->last()['hour'])->toBe(9);
});

it('excludes an hour already tried twice against this lead with no answer', function () {
    seedGlobalBestHours();
    $lead = Lead::factory()->create();
    callLeadAt($lead, 9, daysAgo: 5, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 9, daysAgo: 2, outcome: CallOutcome::Busy);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['failed_hours'])->toBe([9])
        ->and($advice['recommended_hours'])->toBe([11])
        ->and($advice['hours_exhausted'])->toBeFalse();
});

it('does not exclude an hour where this lead was actually connected', function () {
    seedGlobalBestHours();
    $lead = Lead::factory()->create();
    callLeadAt($lead, 9, daysAgo: 5, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 9, daysAgo: 2, outcome: CallOutcome::Connected);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['failed_hours'])->toBe([])
        ->and($advice['recommended_hours'])->toBe([9, 11])
        ->and($advice['ever_connected'])->toBeTrue()
        ->and($advice['connected_hours'])->toBe([9]);
});

it('recommends the global band anyway, flagged exhausted, once every good hour has failed for this lead', function () {
    seedGlobalBestHours();
    $lead = Lead::factory()->create();
    callLeadAt($lead, 9, daysAgo: 5, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 9, daysAgo: 4, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 11, daysAgo: 3, outcome: CallOutcome::Busy);
    callLeadAt($lead, 11, daysAgo: 2, outcome: CallOutcome::Busy);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['hours_exhausted'])->toBeTrue()
        ->and($advice['recommended_hours'])->toBe([9, 11])
        ->and($advice['basis_note'])->toContain('already been tried with this lead');

    expect(app(LeadCallTimingAdvisor::class)->badgeLabel($advice))->toBe('Retry: 9 AM, 11 AM');
});

it('recommends nothing and explains why when the team has no trustworthy data yet', function () {
    $lead = Lead::factory()->create();
    callLeadAt($lead, 9, daysAgo: 1, outcome: CallOutcome::NoAnswer);

    $advisor = app(LeadCallTimingAdvisor::class);
    $advice = $advisor->recommendationFor($lead);

    expect($advice['recommended_hours'])->toBe([])
        ->and($advice['recommended_label'])->toBeNull()
        ->and($advice['basis_note'])->toBe('Not enough team-wide call data yet to suggest a time.')
        ->and($advisor->badgeLabel($advice))->toBeNull();
});

it('formats the badge as "Try:" when qualifying hours remain', function () {
    seedGlobalBestHours();
    $lead = Lead::factory()->create();

    $advisor = app(LeadCallTimingAdvisor::class);
    $badge = $advisor->badgeLabel($advisor->recommendationFor($lead));

    expect($badge)->toBe('Try: 9 AM, 11 AM');
});

it('accepts a pre-computed bestHours collection instead of re-querying', function () {
    seedGlobalBestHours();
    $lead = Lead::factory()->create();
    $bestHours = app(CallTimingMetrics::class)->bestHours();

    // Passing an explicit, pre-computed collection produces the same
    // recommendation as letting recommendationFor() compute it itself.
    $advisor = app(LeadCallTimingAdvisor::class);
    expect($advisor->recommendationFor($lead, $bestHours)['recommended_hours'])
        ->toBe($advisor->recommendationFor($lead)['recommended_hours']);
});

it('shows the "Best time to call" panel on the lead show page', function () {
    seedGlobalBestHours();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create();
    callLeadAt($lead, 9, daysAgo: 2, outcome: CallOutcome::NoAnswer);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Best time to call: 9 AM, 11 AM')
        ->assertSee('No Answer');
});

it('shows a call-timing badge on the Lead Generation list', function () {
    seedGlobalBestHours();
    $manager = User::factory()->role(UserRole::Manager)->create();
    Lead::factory()->create();

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('Try: 9 AM, 11 AM');
});
