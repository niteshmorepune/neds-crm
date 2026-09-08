<?php

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Services\StallReasonMetrics;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->metrics = app(StallReasonMetrics::class);
});

/** Mirrors ObjectionFollowUpDueSourceTest's own fixture-chronology fix. */
function backdateSubjectCreation(string $subjectType, int $subjectId, Carbon $when): void
{
    Activity::where('subject_type', $subjectType)->where('subject_id', $subjectId)->where('event', 'created')->update(['created_at' => $when]);
}

it('lists a tagged, open lead scoped to its owner', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Budget]);
    Lead::factory()->create(['owner_id' => $other->id, 'stall_reason' => StallReason::Trust]);

    $rows = $this->metrics->stallingLeads($sales->id);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['subject_id'])->toBe($lead->id)
        ->and($rows->first()['stall_reason'])->toBe(StallReason::Budget);
});

it('also matches a lead by telecaller_id, not just owner_id', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'owner_id' => null, 'stall_reason' => StallReason::Confused]);

    expect($this->metrics->stallingLeads($telecaller->id)->pluck('subject_id'))->toContain($lead->id);
});

it('excludes a Lost lead even if tagged', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Budget, 'status' => LeadStatus::Lost]);

    expect($this->metrics->stallingLeads($sales->id))->toBeEmpty();
});

it('excludes an untagged lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => null]);

    expect($this->metrics->stallingLeads($sales->id))->toBeEmpty();
});

it('lists a tagged, open deal scoped to its owner, excluding Won/Lost', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    $open = Deal::factory()->create(['owner_id' => $sales->id, 'customer_id' => $customer->id, 'stage' => DealStage::Proposal, 'stall_reason' => StallReason::Competitor]);
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'stall_reason' => StallReason::Budget, 'won_at' => now()]);

    $rows = $this->metrics->stallingDeals($sales->id);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['subject_id'])->toBe($open->id)
        ->and($rows->first()['name'])->toBe('Curamind');
});

it('combines leads and deals in all(), most stale first', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Budget, 'created_at' => now()->subDays(3)]);
    backdateSubjectCreation(Lead::class, $lead->id, now()->subDays(3));

    $customer = Customer::factory()->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'customer_id' => $customer->id, 'stage' => DealStage::Proposal, 'stall_reason' => StallReason::Trust, 'created_at' => now()->subDays(10)]);
    backdateSubjectCreation(Deal::class, $deal->id, now()->subDays(10));

    $all = $this->metrics->all($sales->id);

    expect($all)->toHaveCount(2)
        ->and($all->first()['subject_id'])->toBe($deal->id) // more stale (10d) sorts first
        ->and($all->last()['subject_id'])->toBe($lead->id)
        ->and($all->first()['days_stale'])->toBe(10);
});

it('returns the whole team when no user is given', function () {
    $repA = User::factory()->role(UserRole::Sales)->create();
    $repB = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $repA->id, 'stall_reason' => StallReason::Budget]);
    Lead::factory()->create(['owner_id' => $repB->id, 'stall_reason' => StallReason::Trust]);

    expect($this->metrics->all())->toHaveCount(2);
});

it('tallies counts by reason, descending, only reasons actually present', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->count(2)->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Budget]);
    Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Trust]);

    $counts = $this->metrics->countsByReason();

    expect($counts)->toBe(['budget' => 2, 'trust' => 1])
        ->and($counts)->not->toHaveKey('competitor');
});
