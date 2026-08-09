<?php

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketSatisfactionRating;
use App\Models\User;
use App\Services\ClientHealthMetrics;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->health = app(ClientHealthMetrics::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('scores a client with no flags at a perfect 100', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $customer->notes()->create(['user_id' => $this->admin->id, 'body' => 'Just spoke to them.']);

    expect($this->health->scoreForCustomer($customer))->toBe(100);
});

it('deducts 30 for no contact and 25 for an overdue invoice, stacking both', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue->value]);

    expect($this->health->scoreForCustomer($customer))->toBe(45);
});

it('deducts only 25 for low satisfaction on a client that is otherwise in regular contact', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $customer->notes()->create(['user_id' => $this->admin->id, 'body' => 'Recent touch.']);
    $ticket = Ticket::factory()->create(['customer_id' => $customer->id]);
    TicketSatisfactionRating::create(['ticket_id' => $ticket->id, 'rating' => 1])
        ->forceFill(['created_at' => now()->subDays(5)])->save();

    expect($this->health->scoreForCustomer($customer))->toBe(75);
});

it('never lets the score drop below zero even with every deduction stacked', function () {
    expect($this->health->scoreFor([
        'no_contact' => ['label' => '', 'detail' => ''],
        'overdue_invoice' => ['label' => '', 'detail' => ''],
        'low_satisfaction' => ['label' => '', 'detail' => ''],
        'declining_activity' => ['label' => '', 'detail' => ''],
    ]))->toBe(0);
});

it('does not deduct anything for a growth-opportunity-only flag', function () {
    expect($this->health->scoreFor(['upsell_opportunity' => ['label' => '', 'detail' => '']]))->toBe(100);
});

it('shows the Health Score badge on Client Radar, sorted worst first', function () {
    $this->seed(MenuItemsSeeder::class);
    $atRisk = Customer::factory()->create(['status' => CustomerStatus::Active, 'company_name' => 'At Risk Co']);
    Invoice::factory()->create(['customer_id' => $atRisk->id, 'status' => InvoiceStatus::Overdue->value]);

    $response = $this->actingAs($this->admin)->get(route('client-radar.index'));
    $response->assertOk();

    $rows = $response->viewData('rows');
    expect($rows->contains(fn (array $r) => $r['customer']->id === $atRisk->id && $r['score'] === 45))->toBeTrue();
});

it('shows the Health Score badge on the Client 360 page for an active client', function () {
    $this->seed(MenuItemsSeeder::class);
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $customer->notes()->create(['user_id' => $this->admin->id, 'body' => 'Touch.']);

    $this->actingAs($this->admin)->get(route('clients.show', $customer))
        ->assertOk()
        ->assertSee('Health 100');
});

it('does not show a Health Score on the Client 360 page for an inactive client', function () {
    $this->seed(MenuItemsSeeder::class);
    $customer = Customer::factory()->create(['status' => CustomerStatus::Inactive]);

    $this->actingAs($this->admin)->get(route('clients.show', $customer))
        ->assertOk()
        ->assertDontSee('Health ');
});
