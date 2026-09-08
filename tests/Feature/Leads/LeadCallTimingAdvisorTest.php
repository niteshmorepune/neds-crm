<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadSource;
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

/**
 * Cold Call is not prospect-initiated (LeadSource::isProspectInitiated()),
 * so a lead built with this source never triggers the capture-hour signal
 * — used throughout this file wherever a test wants to isolate the
 * own-attempts/global-band behavior from that separate signal.
 */
function coldCallLead(array $attributes = []): Lead
{
    return Lead::factory()->create(['source' => LeadSource::ColdCall, ...$attributes]);
}

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('falls back to the global best-hour band when the lead has no calls of its own', function () {
    seedGlobalBestHours();
    $lead = coldCallLead();

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['own_attempt_count'])->toBe(0)
        ->and($advice['recommended_hours'])->toBe([9, 11])
        ->and($advice['recommended_label'])->toBe('9 AM, 11 AM')
        ->and($advice['basis_note'])->toContain('based on team-wide calling patterns');
});

it('lists a lead\'s own attempts, most recent first', function () {
    $lead = coldCallLead();
    callLeadAt($lead, 9, daysAgo: 3, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 15, daysAgo: 1, outcome: CallOutcome::Busy);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['own_attempt_count'])->toBe(2)
        ->and($advice['own_attempts']->first()['hour'])->toBe(15)
        ->and($advice['own_attempts']->last()['hour'])->toBe(9);
});

it('excludes an hour already tried twice against this lead with no answer', function () {
    seedGlobalBestHours();
    $lead = coldCallLead();
    callLeadAt($lead, 9, daysAgo: 5, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 9, daysAgo: 2, outcome: CallOutcome::Busy);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['failed_hours'])->toBe([9])
        ->and($advice['recommended_hours'])->toBe([11])
        ->and($advice['hours_exhausted'])->toBeFalse();
});

it('does not exclude an hour where this lead was actually connected', function () {
    seedGlobalBestHours();
    $lead = coldCallLead();
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
    $lead = coldCallLead();
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
    $lead = coldCallLead();
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
    $lead = coldCallLead();

    $advisor = app(LeadCallTimingAdvisor::class);
    $badge = $advisor->badgeLabel($advisor->recommendationFor($lead));

    expect($badge)->toBe('Try: 9 AM, 11 AM');
});

it('accepts a pre-computed bestHours collection instead of re-querying', function () {
    seedGlobalBestHours();
    $lead = coldCallLead();
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
    $lead = coldCallLead();
    callLeadAt($lead, 9, daysAgo: 2, outcome: CallOutcome::NoAnswer);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Best time to call: 9 AM, 11 AM')
        ->assertSee('No Answer');
});

it('shows a call-timing badge on the Lead Generation list', function () {
    seedGlobalBestHours();
    $manager = User::factory()->role(UserRole::Manager)->create();
    coldCallLead();

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('Try: 9 AM, 11 AM');
});

// --- Capture-time signal ------------------------------------------------

it('recommends the lead\'s own capture hour, not diluted by the global band, when it has no calls of its own yet', function () {
    seedGlobalBestHours(); // a 9 AM / 11 AM global band exists but should be set aside here
    $lead = Lead::factory()->create([
        'source' => LeadSource::Website,
        'created_at' => Carbon\Carbon::now('Asia/Kolkata')->setTime(18, 0, 0)->utc(),
    ]);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['capture_hour'])->toBe(['hour' => 18, 'label' => '6 PM', 'source_label' => 'Website'])
        ->and($advice['recommended_hours'])->toBe([18])
        ->and($advice['recommended_label'])->toBe('6 PM')
        ->and($advice['basis_note'])->toBe('No calls logged to this lead yet — it came in via Website around 6 PM, worth trying near then.');
});

it('does not use the capture-hour signal for a source the lead did not choose the timing of', function () {
    $lead = Lead::factory()->create([
        'source' => LeadSource::ColdCall,
        'created_at' => Carbon\Carbon::now('Asia/Kolkata')->setTime(18, 0, 0)->utc(),
    ]);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['capture_hour'])->toBeNull()
        ->and($advice['recommended_hours'])->toBe([])
        ->and($advice['basis_note'])->toBe('Not enough team-wide call data yet to suggest a time.');
});

it('folds the capture hour in alongside the global band once the lead has real call history', function () {
    seedGlobalBestHours(); // 9 AM, 11 AM
    $lead = Lead::factory()->create([
        'source' => LeadSource::Whatsapp,
        'created_at' => Carbon\Carbon::now('Asia/Kolkata')->setTime(18, 0, 0)->utc(),
    ]);
    callLeadAt($lead, 9, daysAgo: 2, outcome: CallOutcome::NoAnswer);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['recommended_hours'])->toBe([9, 11, 18])
        ->and($advice['recommended_label'])->toBe('9 AM, 11 AM, 6 PM')
        ->and($advice['basis_note'])->toContain('came in via WhatsApp around 6 PM');
});

it('excludes the capture hour too once it has failed twice for this lead', function () {
    $lead = Lead::factory()->create([
        'source' => LeadSource::MetaAds,
        'created_at' => Carbon\Carbon::now('Asia/Kolkata')->setTime(18, 0, 0)->utc(),
    ]);
    callLeadAt($lead, 18, daysAgo: 3, outcome: CallOutcome::NoAnswer);
    callLeadAt($lead, 18, daysAgo: 2, outcome: CallOutcome::Busy);

    $advice = app(LeadCallTimingAdvisor::class)->recommendationFor($lead);

    expect($advice['failed_hours'])->toBe([18])
        ->and($advice['hours_exhausted'])->toBeTrue()
        ->and($advice['recommended_hours'])->toBe([18]);

    expect(app(LeadCallTimingAdvisor::class)->badgeLabel($advice))->toBe('Retry: 6 PM');
});

it('marks every prospect-initiated source as eligible for the capture-hour signal, and the rest as not', function () {
    expect(LeadSource::Website->isProspectInitiated())->toBeTrue()
        ->and(LeadSource::Whatsapp->isProspectInitiated())->toBeTrue()
        ->and(LeadSource::MetaAds->isProspectInitiated())->toBeTrue()
        ->and(LeadSource::PhoneEnquiry->isProspectInitiated())->toBeTrue()
        ->and(LeadSource::ColdCall->isProspectInitiated())->toBeFalse()
        ->and(LeadSource::Referral->isProspectInitiated())->toBeFalse()
        ->and(LeadSource::Other->isProspectInitiated())->toBeFalse();
});
