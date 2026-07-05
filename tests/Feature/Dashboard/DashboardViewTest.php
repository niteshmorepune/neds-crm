<?php

use App\Enums\UserRole;
use App\Models\Festival;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows the festival banner when one is within the lead window', function () {
    Festival::factory()->create(['name' => 'Diwali', 'date' => now()->addDays(3)->toDateString()]);
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertSee('Diwali');
});

it('omits the festival banner when nothing is within the window', function () {
    Festival::factory()->create(['name' => 'Far Off Festival', 'date' => now()->addDays(30)->toDateString()]);
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertDontSee('Far Off Festival');
});

it('shows the AI daily digest banner when cached for today', function () {
    $sales = User::factory()->role(UserRole::Sales)->create([
        'ai_daily_digest' => 'You have 2 overdue tasks — tackle those first.',
        'ai_daily_digest_date' => now()->toDateString(),
    ]);

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertSee('You have 2 overdue tasks');
});

it('hides a stale AI daily digest from a previous day', function () {
    $sales = User::factory()->role(UserRole::Sales)->create([
        'ai_daily_digest' => 'Yesterday\'s stale summary text.',
        'ai_daily_digest_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertDontSee('stale summary');
});

it('shows the company dashboard to an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee('Total Clients')
        ->assertSee('Services Overview')
        ->assertSee('Task Summary');
});

it('shows the sales panel to a sales rep', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()
        ->assertSee('Open pipeline by stage')
        ->assertDontSee('Services Overview');
});

it('shows the accounts panel to an accounts user', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('dashboard'))->assertOk()
        ->assertSee('Outstanding receivables');
});

it('shows the support panel to a support user', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('dashboard'))->assertOk()
        ->assertSee('Open tickets by priority');
});
