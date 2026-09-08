<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\ObjectionFollowUpDueSource;
use Illuminate\Support\Carbon;

function objectionFollowUpDueSource(): ObjectionFollowUpDueSource
{
    return app(ObjectionFollowUpDueSource::class);
}

/**
 * lastTouchedAt() floors at both created_at AND the subject's own
 * 'created' Activity row — and that row's created_at is always the real
 * moment it was inserted, ignoring any backdated created_at passed to the
 * model itself (a fixture that creates a Lead "now" but tells it its
 * created_at was 5 days ago still logs a 'created' Activity at the real,
 * current instant). Backdating both the model's own created_at AND its
 * 'created' Activity row is what a genuinely-old, quiet record looks
 * like in production, where both are naturally set at the same real
 * moment in the past — this just simulates that honestly in a test.
 */
function backdateCreation(string $subjectType, int $subjectId, Carbon $when): void
{
    Activity::where('subject_type', $subjectType)->where('subject_id', $subjectId)->where('event', 'created')->update(['created_at' => $when]);
}

/** A stale-tagged lead: created 5 days back, its only touch 4 days back. */
function staleStalledLead(array $overrides = []): Lead
{
    $createdAt = now()->subDays(5);

    $lead = Lead::factory()->create(array_merge([
        'status' => LeadStatus::Contacted,
        'stall_reason' => StallReason::Budget,
        'created_at' => $createdAt,
    ], $overrides));
    backdateCreation(Lead::class, $lead->id, $createdAt);

    CallLog::factory()->create([
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::Connected,
        'called_at' => now()->subDays(4),
    ]);

    return $lead;
}

it('prompts the owner about a stalling lead untouched for 3+ days', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = staleStalledLead(['owner_id' => $sales->id, 'name' => 'Rupesh Kadam']);

    $action = objectionFollowUpDueSource()->next($sales);

    expect($action)->not->toBeNull()
        ->and($action->subjectId)->toBe($lead->id)
        ->and($action->title)->toBe('Budget / financial constraint: Rupesh Kadam')
        ->and($action->actionUrl)->toBe(route('leads.show', $lead));
});

it('also prompts the assigned telecaller, independently of the owner', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = staleStalledLead(['telecaller_id' => $telecaller->id, 'owner_id' => null]);

    expect(objectionFollowUpDueSource()->next($telecaller)?->subjectId)->toBe($lead->id);
});

it('does not prompt before 3 days have passed since the last touch', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Trust]);
    CallLog::factory()->create([
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'called_at' => now()->subDay(),
    ]);

    expect(objectionFollowUpDueSource()->next($sales))->toBeNull();
});

it('does not prompt a lead with no stall reason tagged', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => null]);

    expect(objectionFollowUpDueSource()->next($sales))->toBeNull();
});

it('does not prompt a Lost lead even if tagged', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    staleStalledLead(['owner_id' => $sales->id, 'status' => LeadStatus::Lost]);

    expect(objectionFollowUpDueSource()->next($sales))->toBeNull();
});

it('prompts about a stalling Deal untouched for 3+ days', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    $deal = Deal::factory()->create([
        'owner_id' => $sales->id,
        'customer_id' => $customer->id,
        'stage' => DealStage::Negotiation,
        'stall_reason' => StallReason::Competitor,
        'created_at' => now()->subDays(6),
    ]);
    backdateCreation(Deal::class, $deal->id, now()->subDays(6));
    $deal->notes()->create(['user_id' => $sales->id, 'body' => 'x']);
    $deal->notes()->first()->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

    $action = objectionFollowUpDueSource()->next($sales);

    expect($action->subjectId)->toBe($deal->id)
        ->and($action->title)->toBe('Went with a competitor: Curamind')
        ->and($action->actionUrl)->toBe(route('deals.show', $deal));
});

it('does not prompt a Won or Lost deal even if tagged', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'stall_reason' => StallReason::Budget, 'won_at' => now()]);
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Lost, 'stall_reason' => StallReason::Budget]);

    expect(objectionFollowUpDueSource()->next($sales))->toBeNull();
});

it('picks the most stale candidate across both leads and deals', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $recentLead = staleStalledLead(['owner_id' => $sales->id]); // 4 days stale
    $customer = Customer::factory()->create();
    $staler = Deal::factory()->create([
        'owner_id' => $sales->id, 'customer_id' => $customer->id,
        'stage' => DealStage::Proposal, 'stall_reason' => StallReason::Confused,
        'created_at' => now()->subDays(11),
    ]);
    backdateCreation(Deal::class, $staler->id, now()->subDays(11));
    $staler->notes()->create(['user_id' => $sales->id, 'body' => 'x']);
    $staler->notes()->first()->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    expect(objectionFollowUpDueSource()->next($sales)?->subjectId)->toBe($staler->id);
});

it('excludes a snoozed lead but includes it again once the snooze expires', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = staleStalledLead(['owner_id' => $sales->id]);

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'objection_follow_up_due',
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(objectionFollowUpDueSource()->next($sales))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(objectionFollowUpDueSource()->next($sales)?->subjectId)->toBe($lead->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = staleStalledLead(['owner_id' => $sales->id]);

    objectionFollowUpDueSource()->complete($sales, $lead->id);
})->throws(RuntimeException::class);
