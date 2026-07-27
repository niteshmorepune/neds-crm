<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WeeklyDigest;

it('lets admin and manager view the weekly digest history page', function () {
    WeeklyDigest::factory()->create(['digest_date' => now()->toDateString(), 'summary' => 'All is well.']);

    foreach ([UserRole::Admin, UserRole::Manager] as $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route('reports.weekly-digests'))
            ->assertOk()
            ->assertSee('Weekly Digest History')
            ->assertSee('All is well.');
    }
});

it('forbids sales, support, accounts and intern from the weekly digest history page', function () {
    foreach ([UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Intern] as $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route('reports.weekly-digests'))
            ->assertForbidden();
    }
});

it('renders the empty state when no weekly digests have been recorded yet', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('reports.weekly-digests'))
        ->assertOk()
        ->assertSee('No weekly digests recorded yet');
});

it('lists newest digests first', function () {
    WeeklyDigest::factory()->create(['digest_date' => now()->subWeeks(2)->toDateString(), 'summary' => 'Two weeks ago.']);
    WeeklyDigest::factory()->create(['digest_date' => now()->toDateString(), 'summary' => 'This week.']);

    $admin = User::factory()->role(UserRole::Admin)->create();

    $response = $this->actingAs($admin)->get(route('reports.weekly-digests'));

    $response->assertOk();
    $body = $response->getContent();
    expect(strpos($body, 'This week.'))->toBeLessThan(strpos($body, 'Two weeks ago.'));
});

it('shows a link to the weekly digest history page on the admin dashboard', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('reports.weekly-digests'), false);
});

it('does not show the weekly digest history link on a sales rep dashboard', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('reports.weekly-digests'), false);
});
