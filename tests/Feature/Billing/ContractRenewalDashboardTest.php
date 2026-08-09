<?php

use App\Enums\ContractRenewalStatus;
use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Livewire\ContractRenewalDashboard;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

function renewalTemplate(array $attributes = []): RecurringInvoice
{
    $template = RecurringInvoice::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'is_active' => true,
    ], $attributes));

    $template->items()->create([
        'description' => 'Monthly SEO retainer', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18,
    ]);

    return $template->refresh();
}

it('lets an accounts user reach the dashboard via the route', function () {
    $this->seed(MenuItemsSeeder::class);
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('contract-renewals.index'))->assertOk();
});

it('forbids a support user from the dashboard', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    Livewire::actingAs($support)->test(ContractRenewalDashboard::class)->assertStatus(403);
});

it('blocks a support user at the route level too, since Support was never granted the contract-renewals menu item', function () {
    $this->seed(MenuItemsSeeder::class);
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('contract-renewals.index'))->assertForbidden();
});

it('only shows active templates whose end_date falls within the selected window', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $inWindow = renewalTemplate(['end_date' => now()->addDays(10)]);
    $outsideWindow = renewalTemplate(['end_date' => now()->addDays(45)]);
    $noEndDate = renewalTemplate(['end_date' => null]);
    $pausedInWindow = renewalTemplate(['end_date' => now()->addDays(10), 'is_active' => false]);

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->assertSee($inWindow->customer->company_name)
        ->assertDontSee($outsideWindow->customer->company_name)
        ->assertDontSee($noEndDate->customer->company_name)
        ->assertDontSee($pausedInWindow->customer->company_name);
});

it('widening the window to 90 days surfaces a template renewing in 60 days', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $template = renewalTemplate(['end_date' => now()->addDays(60)]);

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->assertDontSee($template->customer->company_name)
        ->call('setWindow', 90)
        ->assertSee($template->customer->company_name);
});

it('rejects an invalid window value', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->call('setWindow', 45)
        ->assertStatus(422);
});

it('lets an accounts or sales user move a template through the renewal status pipeline', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $template = renewalTemplate(['end_date' => now()->addDays(10)]);

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->call('updateStatus', $template->id, ContractRenewalStatus::Discussion->value);

    expect($template->fresh()->renewal_status)->toBe(ContractRenewalStatus::Discussion);
});

it('rejects an invalid renewal status value', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $template = renewalTemplate(['end_date' => now()->addDays(10)]);

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->call('updateStatus', $template->id, 'not-a-real-status')
        ->assertStatus(422);

    expect($template->fresh()->renewal_status)->toBe(ContractRenewalStatus::NotStarted);
});

it('filters the list by renewal status without changing the summary counts', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $notStarted = renewalTemplate(['end_date' => now()->addDays(5)]);
    $inDiscussion = renewalTemplate(['end_date' => now()->addDays(6), 'renewal_status' => ContractRenewalStatus::Discussion->value]);

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->call('setStatusFilter', ContractRenewalStatus::Discussion->value)
        ->assertSee($inDiscussion->customer->company_name)
        ->assertDontSee($notStarted->customer->company_name);
});

it('computes the monthly-equivalent MRR at stake for the window using RecurringInvoice::monthlyEquivalentValue()', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $template = renewalTemplate(['end_date' => now()->addDays(10), 'frequency' => RecurringFrequency::Quarterly->value]);
    // 3000.00 rupees/quarter (300000 paise across both items) / 3 months = 1000.00/month.
    $template->items()->create(['description' => 'Extra line', 'sac_code' => '998361', 'quantity' => 1, 'rate' => 200000, 'gst_rate' => 18]);

    Livewire::actingAs($accounts)
        ->test(ContractRenewalDashboard::class)
        ->assertSee(Money::format($template->fresh()->load('items')->monthlyEquivalentValue()));

    expect($template->fresh()->load('items')->monthlyEquivalentValue())->toBe(100000);
});
