<?php

use App\Actions\ConvertLead;
use App\Enums\CustomerStatus;
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
        ->and($customer->status)->toBe(CustomerStatus::Prospect)
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

it('promotes a prospect customer to active when their deal is marked won', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($owner);
    $deal = app(ConvertLead::class)->handle($lead);
    $customer = $deal->customer;

    expect($customer->status)->toBe(CustomerStatus::Prospect);

    $deal->moveToStage(DealStage::Won);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

it('creates a quotation from an unconverted lead, converting it first', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['name' => 'Priya Nair', 'company' => 'Nair Traders']);

    $this->actingAs($admin)
        ->post(route('leads.quotation', $lead))
        ->assertRedirect();

    $lead->refresh();
    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_customer_id)->not->toBeNull();

    // Redirect target should be the quotation builder with the new customer pre-filled.
    $response = $this->actingAs($admin)->post(route('leads.quotation', $lead->fresh()));
    $response->assertRedirect();
    expect($response->headers->get('location'))->toContain('customer_id='.$lead->converted_customer_id);
});

it('creates a quotation from an already-converted lead without re-converting', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create();
    $deal = app(ConvertLead::class)->handle($lead);
    $lead->refresh();

    $customerCountBefore = Customer::count();

    $response = $this->actingAs($admin)
        ->post(route('leads.quotation', $lead))
        ->assertRedirect();

    expect(Customer::count())->toBe($customerCountBefore)
        ->and($response->headers->get('location'))->toContain('customer_id='.$lead->converted_customer_id);
});

it('does not downgrade an already-active customer when a deal is won', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $deal = Deal::factory()->for($customer)->create(['stage' => DealStage::Proposal]);

    $this->actingAs($admin);
    $deal->moveToStage(DealStage::Won);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});
