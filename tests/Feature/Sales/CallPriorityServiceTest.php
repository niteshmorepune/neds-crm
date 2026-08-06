<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CallPriorityService;

beforeEach(function () {
    $this->sales = User::factory()->role(UserRole::Sales)->create();
    $this->service = app(CallPriorityService::class);
});

it('ranks a client with no contact and a due follow-up above one recently contacted with no deal', function () {
    $neglected = Customer::factory()->ownedBy($this->sales->id)->create(['company_name' => 'Neglected Co']);
    $neglected->forceFill(['created_at' => now()->subDays(20)])->saveQuietly();
    Deal::factory()->ownedBy($this->sales->id)->stage(DealStage::Negotiation)->create([
        'customer_id' => $neglected->id,
        'next_follow_up_at' => now()->subDay(),
    ]);

    $healthy = Customer::factory()->ownedBy($this->sales->id)->create(['company_name' => 'Healthy Co']);
    CallLog::factory()->create(['callable_id' => $healthy->id, 'callable_type' => Customer::class, 'user_id' => $this->sales->id, 'called_at' => now()]);

    $rows = $this->service->rankedClients($this->sales);

    expect($rows->pluck('company_name')->first())->toBe('Neglected Co');
});

it('excludes clients not owned by this user', function () {
    $otherSales = User::factory()->role(UserRole::Sales)->create();
    Customer::factory()->ownedBy($otherSales->id)->create(['company_name' => 'Not Mine Co']);
    Customer::factory()->ownedBy($this->sales->id)->create(['company_name' => 'Mine Co']);

    $rows = $this->service->rankedClients($this->sales);

    expect($rows->pluck('company_name')->all())->toBe(['Mine Co']);
});

it('excludes inactive clients', function () {
    Customer::factory()->ownedBy($this->sales->id)->inactive()->create(['company_name' => 'Gone Co']);
    Customer::factory()->ownedBy($this->sales->id)->create(['company_name' => 'Active Co']);

    $rows = $this->service->rankedClients($this->sales);

    expect($rows->pluck('company_name')->all())->toBe(['Active Co']);
});

it('ignores Won and Lost deals when computing follow-up due and deal stage', function () {
    $customer = Customer::factory()->ownedBy($this->sales->id)->create();
    Deal::factory()->ownedBy($this->sales->id)->stage(DealStage::Won)->create([
        'customer_id' => $customer->id,
        'next_follow_up_at' => now()->subDay(),
    ]);

    $row = $this->service->rankedClients($this->sales)->first();

    expect($row['follow_up_due'])->toBeFalse()
        ->and($row['top_stage_label'])->toBeNull();
});

it('builds a reason string that reflects the triggered signals', function () {
    $customer = Customer::factory()->ownedBy($this->sales->id)->create();
    Deal::factory()->ownedBy($this->sales->id)->stage(DealStage::Proposal)->create([
        'customer_id' => $customer->id,
        'next_follow_up_at' => now()->subHour(),
    ]);

    $row = $this->service->rankedClients($this->sales)->first();

    expect($row['reason'])
        ->toContain('Follow-up due')
        ->toContain('Proposal (50%)');
});

it('uses days since a low-satisfaction ticket, a call, or a note as the most recent touch', function () {
    $customer = Customer::factory()->ownedBy($this->sales->id)->create();
    Ticket::factory()->for($customer)->create(['created_at' => now()->subDays(2)]);
    $customer->refresh();

    $row = $this->service->rankedClients($this->sales)->first();

    expect($row['days_since_contact'])->toBe(2);
});

it('limits the ranked list to the top 10 clients', function () {
    Customer::factory()->count(15)->ownedBy($this->sales->id)->create();

    $rows = $this->service->rankedClients($this->sales);

    expect($rows)->toHaveCount(10);
});
