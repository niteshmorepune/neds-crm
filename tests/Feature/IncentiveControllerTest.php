<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\IncentiveSetting;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('redirects guests to login', function () {
    $this->get(route('incentives.index'))->assertRedirect('/login');
});

it('renders the incentives page for a Sales rep', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    actingAs($sales)->get(route('incentives.index'))->assertOk();
});

it('renders the incentives page for a Manager', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    actingAs($manager)->get(route('incentives.index'))->assertOk();
});

it('blocks a role with no stake in incentives', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    actingAs($support)->get(route('incentives.index'))->assertForbidden();
});

it('shows a Sales rep only their own sales figure, not another rep\'s', function () {
    $rep = User::factory()->role(UserRole::Sales)->create();
    $otherRep = User::factory()->role(UserRole::Sales)->create();

    Deal::factory()->create([
        'owner_id' => $rep->id,
        'stage' => DealStage::Won,
        'won_at' => now(),
        'value' => 60_000 * 100,
    ]);
    Deal::factory()->create([
        'owner_id' => $otherRep->id,
        'stage' => DealStage::Won,
        'won_at' => now(),
        'value' => 900_000 * 100,
    ]);

    $response = actingAs($rep)->get(route('incentives.index'));

    $response->assertOk()
        ->assertSee(Money::format(60_000 * 100))
        ->assertDontSee(Money::format(900_000 * 100));
});

it('shows a Manager every Sales rep\'s figures', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $rep = User::factory()->role(UserRole::Sales)->create(['name' => 'Priya Rep']);

    Deal::factory()->create([
        'owner_id' => $rep->id,
        'stage' => DealStage::Won,
        'won_at' => now(),
        'value' => 60_000 * 100,
    ]);

    actingAs($manager)->get(route('incentives.index'))
        ->assertOk()
        ->assertSee('Priya Rep');
});

it('lets a Manager update the team bonus pool', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    actingAs($manager)
        ->post(route('incentives.settings.update'), ['team_bonus_pool' => 15000])
        ->assertRedirect();

    expect(IncentiveSetting::current()->team_bonus_pool)->toBe(15000 * 100);
});

it('blocks a Sales rep from updating the team bonus pool', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    actingAs($sales)
        ->post(route('incentives.settings.update'), ['team_bonus_pool' => 15000])
        ->assertForbidden();
});
