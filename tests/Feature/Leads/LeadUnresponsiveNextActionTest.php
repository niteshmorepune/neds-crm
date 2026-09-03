<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\AiAssistant;
use App\Services\CallTimingMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;

function makeUnresponsiveCallOnly(int $count = 3): Lead
{
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    CallLog::factory()->count($count)->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id, 'outcome' => CallOutcome::NoAnswer,
    ]);

    return $lead;
}

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('is null for a lead that is not unresponsive', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);

    expect($lead->suggestedNextAction(app(CallTimingMetrics::class)))->toBeNull();
});

it('recommends trying WhatsApp when only calls have been attempted', function () {
    $lead = makeUnresponsiveCallOnly();

    $message = $lead->suggestedNextAction(app(CallTimingMetrics::class));

    expect($message)->toContain('called 3 time(s)')
        ->and($message)->toContain('never messaged on WhatsApp')
        ->and($message)->toContain('try that channel next');
});

it('recommends calling when only WhatsApp outbound sends have been attempted', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    $lead->notes()->createMany([
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHi, following up"],
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nStill there?"],
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nAny update?"],
    ]);

    $message = $lead->suggestedNextAction(app(CallTimingMetrics::class));

    expect($message)->toContain('messaged on WhatsApp 3 time(s)')
        ->and($message)->toContain('never called')
        ->and($message)->toContain('try calling instead');
});

it('recommends a status update once both channels have genuinely been tried', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    CallLog::factory()->count(2)->create(['callable_type' => Lead::class, 'callable_id' => $lead->id, 'outcome' => CallOutcome::NoAnswer]);
    $lead->notes()->createMany([
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHi, following up"],
    ]);

    $message = $lead->suggestedNextAction(app(CallTimingMetrics::class));

    expect($message)->toContain('Both calls (2) and WhatsApp (1) have been tried')
        ->and($message)->toContain('updating the status below')
        ->and($message)->toContain('Draft follow-up');
});

it('includes the best-time-to-call hint when there is enough real connect-rate data', function () {
    // CallTimingMetrics::summaryLine() needs at least 3 distinct hours each
    // with >= MIN_SAMPLE (15) calls before it'll say anything — matches
    // that service's own test setup, not a smaller stand-in, since this
    // project doesn't mock services (real data throughout).
    $sales = User::factory()->create();
    foreach ([9, 11, 15] as $hour) {
        for ($i = 0; $i < 15; $i++) {
            CallLog::factory()->create([
                'user_id' => $sales->id,
                'direction' => CallDirection::Outgoing,
                'outcome' => $i === 0 ? CallOutcome::Connected : CallOutcome::NoAnswer,
                'called_at' => now()->subDays(2)->setTime($hour, 0),
            ]);
        }
    }

    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    $lead->notes()->createMany([
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHi, following up"],
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nStill there?"],
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nAny update?"],
    ]);

    $message = $lead->suggestedNextAction(app(CallTimingMetrics::class));

    // Only asserting the hint slot is filled in, not its exact wording —
    // CallTimingMetrics::summaryLine()'s own tests cover the phrasing.
    expect($message)->toContain('try calling instead (');
});

it('is a genuine trigger for AiAssistant::suggestLeadStatusUpdate(), not just hasStaleNewStatus()', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['status' => 'lost', 'rationale' => 'No response on either channel after real attempts.'])]],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 15],
        ]),
    ]);
    $lead = makeUnresponsiveCallOnly();
    $lead->notes()->createMany([
        ['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHi, following up"],
    ]);

    expect($lead->hasStaleNewStatus())->toBeFalse() // it's Contacted, not New
        ->and($lead->isUnresponsive())->toBeTrue();

    $result = app(AiAssistant::class)->suggestLeadStatusUpdate($lead);

    expect($result['status'])->toBe(LeadStatus::Lost);
});

it('shows the "next best action" box on the lead show page for an unresponsive lead', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = makeUnresponsiveCallOnly();

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Not responding — next best action')
        ->assertSee('never messaged on WhatsApp');
});

it('does not show the "next best action" box for a lead that is not unresponsive', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Not responding — next best action');
});
