<?php

use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\SalesTarget;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lets admin set company monthly and FY targets', function () {
    $this->actingAs($this->admin)->post(route('sales-dashboard.targets.store'), [
        'company_monthly_target' => '100000',
        'company_fy_target' => '1200000',
    ])->assertRedirect();

    $monthly = SalesTarget::query()->forPeriod(null, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())->first();
    $fy = SalesTarget::query()->forPeriod(null, TargetPeriodType::FinancialYear, TargetPeriodType::FinancialYear->currentPeriodStart())->first();

    expect($monthly->target_value)->toBe(10000000)
        ->and($fy->target_value)->toBe(120000000);
});

it('lets admin set a per-rep monthly target', function () {
    $rep = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($this->admin)->post(route('sales-dashboard.targets.store'), [
        'rep_targets' => [$rep->id => '50000'],
    ])->assertRedirect();

    $target = SalesTarget::query()->forPeriod($rep->id, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())->first();
    expect($target->target_value)->toBe(5000000);
});

it('leaves an existing target untouched when the field is submitted blank', function () {
    SalesTarget::factory()->create([
        'user_id' => null,
        'period_type' => TargetPeriodType::Month,
        'period_start' => TargetPeriodType::Month->currentPeriodStart(),
        'target_value' => 10000000,
    ]);

    $this->actingAs($this->admin)->post(route('sales-dashboard.targets.store'), [
        'company_monthly_target' => '',
    ])->assertRedirect();

    $monthly = SalesTarget::query()->forPeriod(null, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())->first();
    expect($monthly->target_value)->toBe(10000000);
});

it('forbids a sales user from setting targets', function () {
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())
        ->post(route('sales-dashboard.targets.store'), ['company_monthly_target' => '100000'])
        ->assertForbidden();
});
