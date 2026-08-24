<?php

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Service;

it('lists an active recurring template\'s service', function () {
    $customer = Customer::factory()->create();
    $seo = Service::factory()->create(['name' => 'SEO']);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id, 'is_active' => true]);

    expect($customer->fresh()->activeServiceNames()->all())->toBe(['SEO']);
});

it('excludes a paused recurring template\'s service', function () {
    $customer = Customer::factory()->create();
    $seo = Service::factory()->create(['name' => 'SEO']);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id, 'is_active' => false]);

    expect($customer->fresh()->activeServiceNames()->all())->toBe([]);
});

it('lists a non-completed project\'s service', function () {
    $customer = Customer::factory()->create();
    $webDev = Service::factory()->create(['name' => 'Website Development']);
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $webDev->id, 'status' => ProjectStatus::Active]);

    expect($customer->fresh()->activeServiceNames()->all())->toBe(['Website Development']);
});

it('excludes a completed project\'s service', function () {
    $customer = Customer::factory()->create();
    $webDev = Service::factory()->create(['name' => 'Website Development']);
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $webDev->id, 'status' => ProjectStatus::Completed]);

    expect($customer->fresh()->activeServiceNames()->all())->toBe([]);
});

it('deduplicates a service active on both a recurring template and a project, and sorts the result', function () {
    $customer = Customer::factory()->create();
    $seo = Service::factory()->create(['name' => 'SEO']);
    $social = Service::factory()->create(['name' => 'Social Media']);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id, 'is_active' => true]);
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $seo->id, 'status' => ProjectStatus::Active]);
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $social->id, 'status' => ProjectStatus::Active]);

    expect($customer->fresh()->activeServiceNames()->all())->toBe(['SEO', 'Social Media']);
});

it('returns an empty list for a client with no active services', function () {
    $customer = Customer::factory()->create();

    expect($customer->fresh()->activeServiceNames()->all())->toBe([]);
});
