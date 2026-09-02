<?php

use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    config(['services.anthropic.hot_lead_threshold' => 70]);
});

it('defaults to Priority sort, ranking an overdue lead above a merely newer one', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $newer = Lead::factory()->ownedBy($sales->id)->create(['ai_score' => 0, 'status' => LeadStatus::New, 'created_at' => now()]);
    $overdue = Lead::factory()->ownedBy($sales->id)->create([
        'ai_score' => 0, 'status' => LeadStatus::New, 'next_follow_up_at' => now()->subDay(), 'created_at' => now()->subDays(5),
    ]);

    $response = $this->actingAs($sales)->get(route('leads.index'));

    $ids = $response->viewData('leads')->pluck('id')->all();
    expect(array_search($overdue->id, $ids))->toBeLessThan(array_search($newer->id, $ids));
});

it('sorts by Newest when explicitly requested', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $older = Lead::factory()->ownedBy($sales->id)->create(['created_at' => now()->subDays(2)]);
    $newer = Lead::factory()->ownedBy($sales->id)->create(['created_at' => now()]);

    $response = $this->actingAs($sales)->get(route('leads.index', ['sort' => 'newest']));

    $ids = $response->viewData('leads')->pluck('id')->all();
    expect(array_search($newer->id, $ids))->toBeLessThan(array_search($older->id, $ids));
});

it('shows the overdue/due-today/hot-untouched counts on the Needs Attention strip', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->subHour()]);
    Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->addHours(3)]);
    Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => null, 'ai_score' => 85]);

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('1 overdue follow-up')
        ->assertSee('1 due today')
        ->assertSee('1 hot, not yet followed up');
});

it('scopes the Needs Attention strip to the Sales viewer\'s own leads only', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($sales->id)->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->subHour()]);
    Lead::factory()->ownedBy($otherSales->id)->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->subHour()]);

    $this->actingAs($sales)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('1 overdue follow-up'); // only their own, not the other rep's
});

it('does not scope the Needs Attention strip for a Telecaller (shared queue)', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($sales->id)->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->subHour()]);

    $this->actingAs($telecaller)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('1 overdue follow-up'); // sees the shared queue's count, not zero
});

it('filters the list to due-today leads via the attention=due_today link', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $dueToday = Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->addHours(2)]);
    $future = Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->addDays(3)]);

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'due_today']));

    $ids = $response->viewData('leads')->pluck('id')->all();
    expect($ids)->toContain($dueToday->id)->not->toContain($future->id);
});

it('filters the list to hot untouched leads via the attention=hot_untouched link', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $hotUntouched = Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => null, 'ai_score' => 90]);
    $hotButScheduled = Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => now()->addDay(), 'ai_score' => 90]);
    $coldUntouched = Lead::factory()->create(['status' => LeadStatus::New, 'next_follow_up_at' => null, 'ai_score' => 20]);

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'hot_untouched']));

    $ids = $response->viewData('leads')->pluck('id')->all();
    expect($ids)->toContain($hotUntouched->id)
        ->not->toContain($hotButScheduled->id)
        ->not->toContain($coldUntouched->id);
});

it('filters the list to unresponsive leads: 3+ call attempts, never connected, still open', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $unresponsive = Lead::factory()->create(['status' => LeadStatus::Contacted, 'name' => 'Unresponsive Lead']);
    CallLog::factory()->count(3)->create(['callable_type' => Lead::class, 'callable_id' => $unresponsive->id, 'outcome' => CallOutcome::NoAnswer]);

    $tooFewAttempts = Lead::factory()->create(['status' => LeadStatus::Contacted, 'name' => 'Only Called Twice']);
    CallLog::factory()->count(2)->create(['callable_type' => Lead::class, 'callable_id' => $tooFewAttempts->id, 'outcome' => CallOutcome::NoAnswer]);

    $eventuallyConnected = Lead::factory()->create(['status' => LeadStatus::Contacted, 'name' => 'Eventually Connected']);
    CallLog::factory()->count(3)->create(['callable_type' => Lead::class, 'callable_id' => $eventuallyConnected->id, 'outcome' => CallOutcome::NoAnswer]);
    CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $eventuallyConnected->id, 'outcome' => CallOutcome::Connected]);

    $convertedAnyway = Lead::factory()->create(['status' => LeadStatus::Converted, 'name' => 'Converted Despite No Answer']);
    CallLog::factory()->count(3)->create(['callable_type' => Lead::class, 'callable_id' => $convertedAnyway->id, 'outcome' => CallOutcome::NoAnswer]);

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'unresponsive']));

    $ids = $response->viewData('leads')->pluck('id')->all();
    expect($ids)->toContain($unresponsive->id)
        ->not->toContain($tooFewAttempts->id)
        ->not->toContain($eventuallyConnected->id)
        ->not->toContain($convertedAnyway->id);
});

it('counts outbound WhatsApp sends toward the unresponsive threshold, combined with calls', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    // 1 call + 2 WhatsApp sends = 3 combined attempts, never a reply.
    $waAttempts = Lead::factory()->create(['status' => LeadStatus::Contacted, 'name' => 'WhatsApp Attempts Lead']);
    CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $waAttempts->id, 'outcome' => CallOutcome::NoAnswer]);
    $waAttempts->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHi, following up"]);
    $waAttempts->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by AI Assistant (auto-reply)]\nStill there?"]);

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'unresponsive']));

    $ids = $response->viewData('leads')->pluck('id')->all();
    expect($ids)->toContain($waAttempts->id);
});

it('excludes a lead from unresponsive once it has a real inbound WhatsApp reply, even with 3+ prior attempts', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $replied = Lead::factory()->create(['status' => LeadStatus::Contacted, 'name' => 'Replied Via WhatsApp']);
    CallLog::factory()->count(3)->create(['callable_type' => Lead::class, 'callable_id' => $replied->id, 'outcome' => CallOutcome::NoAnswer]);
    // Inbound customer reply: no prefix, user_id null.
    $replied->notes()->create(['user_id' => null, 'body' => 'Yes I am interested, please call tomorrow']);

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'unresponsive']));

    expect($response->viewData('leads')->pluck('id')->all())->not->toContain($replied->id);
});

it('does not mistake a manually-typed staff note for a WhatsApp signal (real user_id, no prefix)', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    // 3 manual staff notes, no calls, no real WhatsApp activity at all —
    // must NOT count as 3 attempts (a manual note has a real user_id).
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted, 'name' => 'Only Manual Notes']);
    $lead->notes()->createMany([
        ['user_id' => $manager->id, 'body' => 'Called office, will try again'],
        ['user_id' => $manager->id, 'body' => 'Checked LinkedIn'],
        ['user_id' => $manager->id, 'body' => 'Reminder set'],
    ]);

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'unresponsive']));

    expect($response->viewData('leads')->pluck('id')->all())->not->toContain($lead->id);
});

it('shows the unresponsive count on the Needs Attention strip', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $unresponsive = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    CallLog::factory()->count(3)->create(['callable_type' => Lead::class, 'callable_id' => $unresponsive->id, 'outcome' => CallOutcome::NoAnswer]);

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('1 unresponsive (3+ attempts, no reply)');
});

it('shows an Overdue badge on the list row for a lead with a past follow-up date', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    Lead::factory()->create(['name' => 'Stale Lead Co', 'status' => LeadStatus::New, 'next_follow_up_at' => now()->subDay()]);

    $this->actingAs($manager)->get(route('leads.index', ['sort' => 'newest']))
        ->assertOk()
        ->assertSee('Overdue');
});

it('flags a New lead with a note as having a stale status, but not a genuinely untouched New lead', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $stale = Lead::factory()->create(['name' => 'Typed A Note Instead', 'status' => LeadStatus::New]);
    $stale->notes()->create(['user_id' => $manager->id, 'body' => 'Called, no answer']);
    $fresh = Lead::factory()->create(['name' => 'Genuinely Untouched', 'status' => LeadStatus::New]);

    $response = $this->actingAs($manager)->get(route('leads.index', ['sort' => 'newest']));

    $response->assertOk();
    expect($stale->hasStaleNewStatus())->toBeTrue();
    expect($fresh->hasStaleNewStatus())->toBeFalse();

    $leads = $response->viewData('leads')->keyBy('id');
    expect($leads[$stale->id]->hasStaleNewStatus())->toBeTrue();
    expect($leads[$fresh->id]->hasStaleNewStatus())->toBeFalse();
});

it('flags a New lead with a call log (but no note) as stale too', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $lead->id, 'outcome' => CallOutcome::NoAnswer]);

    expect($lead->hasStaleNewStatus())->toBeTrue();
});

it('never flags a lead already past New as having a stale status', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    $lead->notes()->create(['user_id' => $user->id, 'body' => 'Some note']);

    expect($lead->hasStaleNewStatus())->toBeFalse();
});

it('shows the "status may need updating" count on the Needs Attention strip and filters via attention=stale_status', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $stale = Lead::factory()->create(['name' => 'Stale Status Lead', 'status' => LeadStatus::New]);
    $stale->notes()->create(['user_id' => $manager->id, 'body' => 'Talked to him yesterday']);
    $fresh = Lead::factory()->create(['status' => LeadStatus::New]);

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('1 status may need updating');

    $response = $this->actingAs($manager)->get(route('leads.index', ['attention' => 'stale_status']));
    $ids = $response->viewData('leads')->pluck('id')->all();
    expect($ids)->toContain($stale->id)->not->toContain($fresh->id);
});
