<?php

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\QuarterlyAward;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('renders the index page for an admin, a sales user, and a role with nothing to show yet', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('quarterly-awards.index'))->assertOk();
})->with([
    'admin' => UserRole::Admin,
    'sales with nothing yet' => UserRole::Sales,
    'support with nothing yet' => UserRole::Support,
]);

it('shows a manager every award, but a regular user only their own approved ones', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $winner = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice Winner']);
    $other = User::factory()->role(UserRole::Sales)->create();

    $approved = QuarterlyAward::factory()->approved()->create(['user_id' => $winner->id, 'quarter' => 1]);
    $pending = QuarterlyAward::factory()->create(['user_id' => $winner->id, 'status' => AwardStatus::Pending, 'quarter' => 2]);

    $this->actingAs($manager)->get(route('quarterly-awards.index'))
        ->assertOk()->assertSee('Alice Winner');

    $this->actingAs($winner)->get(route('quarterly-awards.index'))
        ->assertOk()->assertSee($approved->title())->assertDontSee($pending->title().' — '.$pending->periodLabel());

    $this->actingAs($other)->get(route('quarterly-awards.index'))
        ->assertOk()->assertDontSee('Alice Winner');
});

it('lets a manager regenerate a quarter', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $alice = User::factory()->role(UserRole::Sales)->create();
    User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->count(2)->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    $this->actingAs($manager)
        ->post(route('quarterly-awards.regenerate'), ['financial_year' => '2026-27', 'quarter' => 2])
        ->assertRedirect(route('quarterly-awards.index'));

    expect(QuarterlyAward::where('financial_year', '2026-27')->where('quarter', 2)->count())->toBeGreaterThan(0);
});

it('forbids a regular user from regenerating', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)
        ->post(route('quarterly-awards.regenerate'), ['financial_year' => '2026-27', 'quarter' => 2])
        ->assertForbidden();
});

it('streams the certificate PDF only once approved', function () {
    $winner = User::factory()->role(UserRole::Sales)->create();
    $pending = QuarterlyAward::factory()->create(['user_id' => $winner->id, 'status' => AwardStatus::Pending, 'quarter' => 1]);
    $approved = QuarterlyAward::factory()->approved()->create(['user_id' => $winner->id, 'quarter' => 2]);

    $this->actingAs($winner)->get(route('quarterly-awards.certificate', $pending))->assertForbidden();
    $this->actingAs($winner)->get(route('quarterly-awards.certificate', $approved))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('forbids a non-winning, non-manager user from downloading someone else\'s certificate', function () {
    $someone = User::factory()->role(UserRole::Sales)->create();
    $approved = QuarterlyAward::factory()->approved()->create();

    $this->actingAs($someone)->get(route('quarterly-awards.certificate', $approved))->assertForbidden();
});
