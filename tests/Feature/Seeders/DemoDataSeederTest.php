<?php

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('seeds realistic cross-module demo data', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Customer::count())->toBeGreaterThan(0)
        ->and(Lead::where('status', LeadStatus::Converted)->whereNotNull('converted_at')->count())->toBeGreaterThan(0)
        ->and(Deal::count())->toBeGreaterThan(0)
        ->and(Invoice::whereNotNull('recurring_invoice_id')->count())->toBeGreaterThan(0);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Call run() directly to test the seeder's own guard (db:seed adds its own
    // production confirmation on top, which is separate).
    (new DemoDataSeeder)->run();

    expect(Customer::count())->toBe(0);

    app()->detectEnvironment(fn () => 'testing');
});
