<?php

use App\Enums\UserRole;
use App\Models\HiddenDashboardWidget;
use App\Models\User;

it('shows every widget in the viewers own panel catalog, all checked by default', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard-widget-settings.edit'))
        ->assertOk()
        ->assertSee('Follow-ups due')
        ->assertSee('Won this month')
        ->assertSee('Who to Call Today')
        ->assertDontSee('Total Clients'); // admin-panel widget, not sales
});

it('hides a widget from the dashboard once unchecked and saved', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $allExceptFollowups = ['won_this_month', 'pipeline_table', 'call_priority_list', 'overdue_follow_ups', 'my_productivity'];
    $this->actingAs($sales)->put(route('dashboard-widget-settings.update'), ['visible' => $allExceptFollowups])
        ->assertRedirect(route('dashboard'));

    expect(HiddenDashboardWidget::where('user_id', $sales->id)->pluck('widget_key')->all())->toBe(['followups_due']);

    $this->actingAs($sales)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Follow-ups due')
        ->assertSee('Won this month');
});

it('leaves every widget visible again once all boxes are re-checked', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    HiddenDashboardWidget::create(['user_id' => $sales->id, 'widget_key' => 'followups_due']);

    $all = ['followups_due', 'won_this_month', 'pipeline_table', 'call_priority_list', 'overdue_follow_ups', 'my_productivity'];
    $this->actingAs($sales)->put(route('dashboard-widget-settings.update'), ['visible' => $all]);

    expect(HiddenDashboardWidget::where('user_id', $sales->id)->count())->toBe(0);
});

it('silently ignores a widget key outside the viewers own panel catalog, instead of storing it', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->put(route('dashboard-widget-settings.update'), [
        'visible' => ['won_this_month', 'pipeline_table', 'call_priority_list', 'overdue_follow_ups', 'my_productivity', 'clients_total'],
    ]);

    // followups_due wasn't submitted, so it's hidden; the bogus admin-panel
    // key is dropped rather than stored (it isn't in the Sales catalog).
    expect(HiddenDashboardWidget::where('user_id', $sales->id)->pluck('widget_key')->all())->toBe(['followups_due']);
});

it('only affects the viewers own dashboard, not another users', function () {
    $sales1 = User::factory()->role(UserRole::Sales)->create();
    $sales2 = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales1)->put(route('dashboard-widget-settings.update'), [
        'visible' => ['won_this_month', 'pipeline_table', 'call_priority_list', 'overdue_follow_ups', 'my_productivity'],
    ]);

    $this->actingAs($sales2)->get(route('dashboard'))->assertOk()->assertSee('Follow-ups due');
});

it('requires authentication to view or update dashboard widget settings', function () {
    $this->get(route('dashboard-widget-settings.edit'))->assertRedirect(route('login'));
    $this->put(route('dashboard-widget-settings.update'), ['visible' => []])->assertRedirect(route('login'));
});
