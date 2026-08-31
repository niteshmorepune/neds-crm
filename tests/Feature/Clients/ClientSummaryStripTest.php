<?php

use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

function recurringTemplateFor(Customer $client, array $attributes = []): RecurringInvoice
{
    $template = RecurringInvoice::factory()->create(array_merge(['customer_id' => $client->id], $attributes));
    $template->items()->create([
        'description' => 'Monthly SEO retainer', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18,
    ]);

    return $template->refresh();
}

it('shows MRR summed across the clients active recurring templates only', function () {
    $client = Customer::factory()->create();
    recurringTemplateFor($client, ['is_active' => true, 'frequency' => RecurringFrequency::Monthly->value]);
    recurringTemplateFor($client, ['is_active' => false, 'frequency' => RecurringFrequency::Monthly->value]);

    $client->load('recurringInvoices.items');
    expect($client->monthlyRecurringValue())->toBe(100000); // one active ₹1,000/mo template only
});

it('normalizes a quarterly templates cycle amount to its monthly equivalent for MRR', function () {
    $client = Customer::factory()->create();
    recurringTemplateFor($client, ['is_active' => true, 'frequency' => RecurringFrequency::Quarterly->value]);

    $client->load('recurringInvoices.items');
    expect($client->monthlyRecurringValue())->toBe((int) round(100000 / 3));
});

it('picks the soonest next_run_on among active templates as the next renewal date', function () {
    $client = Customer::factory()->create();
    recurringTemplateFor($client, ['is_active' => true, 'next_run_on' => now()->addDays(60)->toDateString()]);
    recurringTemplateFor($client, ['is_active' => true, 'next_run_on' => now()->addDays(10)->toDateString()]);
    recurringTemplateFor($client, ['is_active' => false, 'next_run_on' => now()->addDays(1)->toDateString()]); // inactive, ignored

    $client->load('recurringInvoices');
    expect($client->nextRenewalDate()->toDateString())->toBe(now()->addDays(10)->toDateString());
});

it('returns null next renewal when no active template exists', function () {
    $client = Customer::factory()->create();
    recurringTemplateFor($client, ['is_active' => false]);

    $client->load('recurringInvoices');
    expect($client->nextRenewalDate())->toBeNull();
});

it('next renewal date always matches next billing date, even when end_date disagrees with next_run_on', function () {
    // Real production report, 2026-08-31: two live clients each showed a
    // "Next Renewal" tile a full year apart from the "Next billing" figure
    // on the same page, because the two were computed from different
    // columns (end_date vs next_run_on) that had drifted apart in real
    // data (a template billed past its own original end_date without
    // end_date being updated to match). Both tiles must always agree.
    $client = Customer::factory()->create();
    recurringTemplateFor($client, [
        'is_active' => true,
        'end_date' => now()->addDays(10)->toDateString(),
        'next_run_on' => now()->addDays(400)->toDateString(),
    ]);

    $client->load('recurringInvoices');
    expect($client->nextRenewalDate()->toDateString())->toBe(now()->addDays(400)->toDateString())
        ->not->toBe(now()->addDays(10)->toDateString());
});

it('shows MRR and next renewal on the client page for a role without invoice access', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $client = Customer::factory()->create(['company_name' => 'Ganesh Auto Parts']);
    recurringTemplateFor($client, ['is_active' => true]);

    $this->actingAs($support)->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('MRR')
        ->assertSee(Money::format(100000), false)
        ->assertDontSee('Total Revenue');
});

it('renders the same date for the page-top "Next Renewal" tile and the Services tab "Next billing" tile', function () {
    $client = Customer::factory()->create();
    recurringTemplateFor($client, [
        'is_active' => true,
        'end_date' => now()->addDays(10)->toDateString(),
        'next_run_on' => now()->addDays(400)->toDateString(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

    // The two tiles must show the same next_run_on-derived date -- while
    // end_date (still shown separately, in the recurring-services table's
    // own "End date" column) legitimately stays a different value.
    $response->assertOk()
        ->assertSeeInOrder([now()->addDays(400)->format('d M Y'), now()->addDays(400)->format('d M Y')])
        ->assertSee(now()->addDays(10)->format('d M Y'));
});

it('shows total revenue and outstanding to a role with invoice access', function () {
    $client = Customer::factory()->create();
    Invoice::factory()->for($client)->create(['total' => 500000, 'amount_paid' => 200000]);

    $this->actingAs($this->admin)->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Total Revenue')
        ->assertSee(Money::format(500000), false)
        ->assertSee('Outstanding');
});

it('sums outstanding using the same query as the Receivables Report, so the two can never disagree', function () {
    $client = Customer::factory()->create();
    $invoice = Invoice::factory()->for($client)->create(['status' => InvoiceStatus::Sent->value, 'total' => 300000, 'amount_paid' => 100000]);
    Invoice::factory()->for($client)->create(['status' => InvoiceStatus::Paid->value, 'total' => 200000, 'amount_paid' => 200000]);

    $response = $this->actingAs($this->admin)->get(route('clients.show', $client));

    $response->assertOk()->assertSee(Money::format($invoice->balance()), false);
});
