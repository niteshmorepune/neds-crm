<?php

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
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

it('shows the AI weekly owner digest banner to a manager when cached for today', function () {
    $manager = User::factory()->role(UserRole::Manager)->create([
        'ai_weekly_digest' => 'Pipeline is healthy; two clients need a check-in this week.',
        'ai_weekly_digest_date' => now()->toDateString(),
    ]);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertSee('two clients need a check-in');
});

it('hides the weekly owner digest banner from a Sales rep even when somehow cached', function () {
    $sales = User::factory()->role(UserRole::Sales)->create([
        'ai_weekly_digest' => 'A weekly digest that should never reach a rep.',
        'ai_weekly_digest_date' => now()->toDateString(),
    ]);

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertDontSee('should never reach a rep');
});

it('hides a stale AI weekly digest from a previous week', function () {
    $manager = User::factory()->role(UserRole::Manager)->create([
        'ai_weekly_digest' => 'Last week\'s stale synthesis.',
        'ai_weekly_digest_date' => now()->subWeek()->toDateString(),
    ]);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertDontSee('stale synthesis');
});

it('shows the AI Recommendations section with a link to team coaching insights for a manager', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('AI Recommendations')
        ->assertSee('Team coaching insights');
});

it('shows a fallback message in AI Recommendations when no weekly digest is cached yet', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No new digest yet today');
});

it('hides the AI Recommendations section entirely from a Sales rep', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertDontSee('AI Recommendations');
});

it('shows the company dashboard to an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee('Total Clients')
        ->assertSee('Total Leads')
        ->assertSee('Services Overview')
        ->assertSee('Task Summary');
});

it('shows the sales panel to a sales rep', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()
        ->assertSee('Open pipeline by stage')
        ->assertDontSee('Services Overview');
});

it('shows the who-to-call-today widget on the sales panel, ranking a neglected client first', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $neglected = Customer::factory()->ownedBy($sales->id)->create(['company_name' => 'Neglected Co']);
    $neglected->forceFill(['created_at' => now()->subDays(25)])->saveQuietly();
    Customer::factory()->ownedBy($sales->id)->create(['company_name' => 'Fresh Co'])
        ->forceFill(['created_at' => now()])->saveQuietly();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()
        ->assertSee('Who to Call Today')
        ->assertSeeInOrder(['Neglected Co', 'Fresh Co']);
});

it('shows the accounts panel to an accounts user', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('dashboard'))->assertOk()
        ->assertSee('Outstanding receivables')
        ->assertSee('Revenue report');
});

it('links the admin stat cards, task summary, and services panel to their filtered list views', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee(route('clients.index', ['status' => 'all']))
        ->assertSee(route('clients.index', ['status' => CustomerStatus::Active->value]))
        ->assertSee(route('clients.index', ['status' => CustomerStatus::Inactive->value]))
        ->assertSee(route('leads.index'))
        ->assertSee(route('tasks.index', ['type' => 'all']))
        ->assertSee(route('tasks.index', ['type' => 'all', 'pending' => 1]))
        ->assertSee(route('tasks.index', ['type' => 'all', 'overdue' => 1]))
        ->assertSee(route('tasks.index', ['type' => 'all', 'status' => TaskStatus::Done->value]))
        ->assertSee(route('projects.index', ['group' => 'service']));
});

it('links the sales dashboard tiles to leads/deals views', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()
        ->assertSee(route('leads.index', ['follow_up_due' => 1]))
        ->assertSee(route('deals.index'));
});

it('links the support dashboard tiles to filtered tickets/tasks views', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('dashboard'))->assertOk()
        ->assertSee(route('tickets.index', ['mine' => 1, 'open' => 1]))
        ->assertSee(route('tickets.index', ['mine' => 1, 'at_risk' => 1]))
        ->assertSee(route('tasks.index', ['mine' => 1, 'type' => 'all']))
        ->assertSee(route('tasks.index', ['mine' => 1, 'type' => 'all', 'pending' => 1]))
        ->assertSee(route('tasks.index', ['mine' => 1, 'type' => 'all', 'overdue' => 1]));
});

it('links the intern dashboard completed-today tile to the filtered tasks view', function () {
    $intern = User::factory()->role(UserRole::Intern)->create();

    $this->actingAs($intern)->get(route('dashboard'))->assertOk()
        ->assertSee(route('tasks.index', ['mine' => 1, 'type' => 'all', 'completed_today' => 1]))
        ->assertSee(route('tasks.index', ['mine' => 1, 'type' => 'all', 'pending' => 1]));
});

it('links the telecaller dashboard tiles to filtered leads/calls views', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    $this->actingAs($telecaller)->get(route('dashboard'))->assertOk()
        ->assertSee(route('leads.index', ['status' => LeadStatus::New->value]))
        ->assertSee(route('calls.index', ['pending_followup' => 1]));
});

it('links the overdue invoices count to the filtered invoices list', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('dashboard'))->assertOk()
        ->assertSee(route('invoices.index', ['status' => 'overdue']), false);
});

it('links the collected-this-month figure to the payments breakdown', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('dashboard'))->assertOk()
        ->assertSee(route('reports.collected'), false);
});

it('shows the support panel to a support user', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('dashboard'))->assertOk()
        ->assertSee('Open tickets by priority');
});

it('shows total/pending/overdue task counts on the support dashboard', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('dashboard'))->assertOk()
        ->assertSee('Total tasks')
        ->assertSee('Pending tasks')
        ->assertSee('Overdue tasks');
});

it('filters the sales dashboards Won-this-month figure via the month query param', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'value' => 500000, 'won_at' => now()->subMonths(2)]);

    $twoMonthsAgo = now()->subMonths(2)->format('Y-m');

    $this->actingAs($sales)->get(route('dashboard', ['month' => $twoMonthsAgo]))
        ->assertOk()
        ->assertSee('Won '.now()->subMonths(2)->format('M Y'))
        ->assertSee('₹5,000.00', false);
});

it('falls back to the current month on a malformed month param, instead of erroring', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard', ['month' => 'not-a-month']))
        ->assertOk()
        ->assertSee('Won this month');
});

it('shows the month-filter quick-jump chips on the sales and accounts dashboards', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertSee('This month');
    $this->actingAs($accounts)->get(route('dashboard'))->assertOk()->assertSee('This month');
});

it('does not show a month filter on dashboards with nothing period-scoped to filter', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('This month');
    $this->actingAs($support)->get(route('dashboard'))->assertOk()->assertDontSee('This month');
});

it('ignores an extraneous month param on the admin dashboard without erroring', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard', ['month' => now()->subMonth()->format('Y-m')]))
        ->assertOk()
        ->assertSee('Total Clients');
});

it('keeps showing the support panel even when Sales is granted as an additional role', function () {
    // Regression test: DashboardController is deliberately keyed on the
    // primary role only ($user->role), not hasRole() — a secondary role must
    // never change which dashboard panel someone lands on.
    $support = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();

    $this->actingAs($support)->get(route('dashboard'))->assertOk()
        ->assertSee('Open tickets by priority')
        ->assertDontSee('Open pipeline by stage');
});
