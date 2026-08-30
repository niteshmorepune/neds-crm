<?php

use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager view the Billing Settings page but forbids a sales user', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('billing-settings.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('billing-settings.index'))->assertForbidden();
});

it('defaults the billing SAC/HSN code to 998314 the first time it is read', function () {
    expect(BillingSetting::current()->default_sac_code)->toBe('998314');
});

it('lets a manager update the default SAC/HSN code but forbids a sales user', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->patch(route('billing-settings.sac-default.update'), [
        'default_sac_code' => '998313',
    ])->assertRedirect();

    expect(BillingSetting::current()->default_sac_code)->toBe('998313')
        ->and(BillingSetting::current()->updated_by)->toBe($manager->id);

    $this->actingAs(User::factory()->role(UserRole::Sales)->create())
        ->patch(route('billing-settings.sac-default.update'), ['default_sac_code' => '000000'])
        ->assertForbidden();
});

it('lets a manager catch the invoice numbering counters up to a given next number, per sequence type', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->patch(route('billing-settings.invoice-numbering.update'), [
        'financial_year' => '2026-27',
        'next_domestic_number' => 40,
        'next_export_number' => 22,
    ])->assertRedirect();

    $generator = app(InvoiceNumberGenerator::class);
    $domestic = $generator->generate(Carbon::parse('2026-06-10'));
    $export = $generator->generate(Carbon::parse('2026-06-10'), isOverseas: true);

    expect($domestic)->toBe('26/27-040')
        ->and($export)->toBe('26/27-IN022');

    $this->actingAs(User::factory()->role(UserRole::Sales)->create())
        ->patch(route('billing-settings.invoice-numbering.update'), [
            'financial_year' => '2026-27', 'next_domestic_number' => 1, 'next_export_number' => 1,
        ])->assertForbidden();
});
