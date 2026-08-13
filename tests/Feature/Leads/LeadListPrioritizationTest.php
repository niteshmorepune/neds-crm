<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
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

it('shows an Overdue badge on the list row for a lead with a past follow-up date', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    Lead::factory()->create(['name' => 'Stale Lead Co', 'status' => LeadStatus::New, 'next_follow_up_at' => now()->subDay()]);

    $this->actingAs($manager)->get(route('leads.index', ['sort' => 'newest']))
        ->assertOk()
        ->assertSee('Overdue');
});
