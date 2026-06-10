<?php

use App\Actions\ConvertLead;
use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('converts a lead into a customer, primary contact and deal in one transaction', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $service = Service::factory()->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create([
        'name' => 'Ravi Kumar',
        'company' => 'Kumar Industries',
        'email' => 'ravi@kumar.test',
        'phone' => '9876543210',
        'service_id' => $service->id,
        'estimated_value' => 750000,
    ]);

    $this->actingAs($owner);
    $deal = app(ConvertLead::class)->handle($lead);

    $customer = Customer::firstWhere('company_name', 'Kumar Industries');

    expect($customer)->not->toBeNull()
        ->and($customer->owner_id)->toBe($owner->id)
        ->and($customer->primaryContact?->name)->toBe('Ravi Kumar')
        ->and($customer->contacts()->where('is_primary', true)->count())->toBe(1);

    expect($deal)->toBeInstanceOf(Deal::class)
        ->and($deal->customer_id)->toBe($customer->id)
        ->and($deal->stage)->toBe(DealStage::New)
        ->and($deal->value)->toBe(750000)
        ->and($deal->service_id)->toBe($service->id)
        ->and($deal->lead_id)->toBe($lead->id);

    $lead->refresh();
    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_customer_id)->toBe($customer->id)
        ->and($lead->converted_deal_id)->toBe($deal->id);

    // A breadcrumb note lands on the new client's timeline.
    expect($customer->notes()->count())->toBe(1);
});

it('blocks converting an already-converted lead via the action', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted]);

    expect(fn () => app(ConvertLead::class)->handle($lead))
        ->toThrow(RuntimeException::class);
});

it('converts via the HTTP route and is idempotent', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($admin)->post(route('leads.convert', $lead))->assertRedirect();
    expect($lead->fresh()->status)->toBe(LeadStatus::Converted);

    $customersAfterFirst = Customer::count();

    // Second attempt should not create another customer/deal.
    $this->actingAs($admin)->post(route('leads.convert', $lead))->assertRedirect();
    expect(Customer::count())->toBe($customersAfterFirst);
});
