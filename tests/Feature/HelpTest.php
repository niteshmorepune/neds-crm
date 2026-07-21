<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows the help landing with role-recommended guides', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('help'))->assertOk()
        ->assertSee('Getting Started')
        ->assertSee('Sales');
});

it('renders a guide from its markdown', function () {
    $user = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($user)->get(route('help.show', 'support'))->assertOk()
        ->assertSee('Support guide')      // the markdown H1
        ->assertSee('Tickets');           // section content
});

it('rewrites cross-guide links to in-app help routes', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    // getting-started.md links to sales.md etc. — they should point at /help/...
    $this->actingAs($admin)->get(route('help.show', 'getting-started'))->assertOk()
        ->assertSee(route('help.show', 'sales'), false)
        ->assertDontSee('sales.md');
});

it('assigns a plain id to headings so cross-guide anchor links actually scroll to their section', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    // troubleshooting.md links to accounts.md#3-recurring-invoices — the
    // link rewrite alone isn't enough if the target guide's heading has no
    // matching id, since the browser would just land at the top of the page.
    $this->actingAs($admin)->get(route('help.show', 'troubleshooting'))->assertOk()
        ->assertSee(route('help.show', 'accounts').'#3-recurring-invoices', false);

    $this->actingAs($admin)->get(route('help.show', 'accounts'))->assertOk()
        ->assertSee('id="3-recurring-invoices"', false);
});

it('404s for an unknown guide (no path traversal)', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/help/nope')->assertNotFound();
    $this->actingAs($admin)->get('/help/..%2f..%2f.env')->assertNotFound();
});

it('shows a Help link in the top bar', function () {
    $user = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Help');
});
