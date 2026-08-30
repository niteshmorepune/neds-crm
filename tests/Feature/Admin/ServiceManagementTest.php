<?php

use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\Deal;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Database\Seeders\ServicesSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('seeds all 8 NEDS service lines, including AMC Service and Performance Marketing', function () {
    $this->seed(ServicesSeeder::class);

    expect(Service::pluck('name')->sort()->values()->all())->toBe([
        'AI Automation',
        'AMC Service',
        'GMB',
        'Performance Marketing',
        'SEO',
        'Social Media',
        'Software Development',
        'Website Design & Development',
    ]);
});

it('lets a manager manage services but forbids a sales user', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('services.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('services.index'))->assertForbidden();
});

it('adds a service with an auto slug and next sort order', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('services.store'), ['name' => 'AI Automation'])->assertRedirect();

    $service = Service::firstWhere('name', 'AI Automation');
    expect($service)->not->toBeNull()
        ->and($service->slug)->toBe('ai-automation')
        ->and($service->is_active)->toBeFalse(); // checkbox not sent on the add form
});

it('renames and deactivates a service', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $service = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);

    $this->actingAs($admin)->put(route('services.update', $service), [
        'name' => 'Search Engine Optimization',
        'sort_order' => 2,
        // is_active omitted => deactivate
    ])->assertRedirect();

    $service->refresh();
    expect($service->name)->toBe('Search Engine Optimization')
        ->and($service->is_active)->toBeFalse();
});

it('refuses to delete a service that is in use', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $service = Service::factory()->create();
    Deal::factory()->create(['service_id' => $service->id]);

    $this->actingAs($admin)->delete(route('services.destroy', $service));

    expect(Service::find($service->id))->not->toBeNull();
});

it('deletes an unused service', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $service = Service::factory()->create();

    $this->actingAs($admin)->delete(route('services.destroy', $service))->assertRedirect();

    expect(Service::find($service->id))->toBeNull();
});

it('defaults the billing SAC/HSN code to 998314 the first time it is read', function () {
    expect(BillingSetting::current()->default_sac_code)->toBe('998314');
});

it('lets a manager update the default SAC/HSN code but forbids a sales user', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->patch(route('services.billing-settings.update'), [
        'default_sac_code' => '998313',
    ])->assertRedirect();

    expect(BillingSetting::current()->default_sac_code)->toBe('998313')
        ->and(BillingSetting::current()->updated_by)->toBe($manager->id);

    $this->actingAs(User::factory()->role(UserRole::Sales)->create())
        ->patch(route('services.billing-settings.update'), ['default_sac_code' => '000000'])
        ->assertForbidden();
});
