<?php

use App\Enums\UserRole;
use App\Livewire\MyProductivity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('shows the viewer their own rank and score, never a peer\'s name', function () {
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    $bob = User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    Livewire::actingAs($bob)
        ->test(MyProductivity::class)
        ->assertSet('row.user', 'Bob')
        ->assertSee('of 2')
        ->assertDontSee('Alice');
});

it('shows a not-enough-peers note when the viewer\'s role group has fewer than 2 people', function () {
    $onlyAccountant = User::factory()->role(UserRole::Accounts)->create();

    Livewire::actingAs($onlyAccountant)
        ->test(MyProductivity::class)
        ->assertSee('Not enough peers');
});

it('lets a staff member get an AI tip for themselves only', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Try logging a few more calls this week.']],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 15],
        ]),
    ]);
    $alice = User::factory()->role(UserRole::Sales)->create();
    $bob = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    Livewire::actingAs($bob)
        ->test(MyProductivity::class)
        ->call('getTip')
        ->assertSet('tip', 'Try logging a few more calls this week.');
});

it('does not render on the Admin/Manager dashboard', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertDontSee('Your Productivity This Month');
});

it('renders on Sales/Support/Accounts/Intern dashboards', function () {
    foreach ([UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Intern] as $role) {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Your Productivity This Month');
    }
});
