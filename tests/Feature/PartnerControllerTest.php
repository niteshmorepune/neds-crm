<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('admin can list partners', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.index'))
        ->assertOk();
});

it('manager can list partners', function () {
    actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->get(route('partners.index'))
        ->assertOk();
});

it('sales cannot access partners', function () {
    actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->get(route('partners.index'))
        ->assertForbidden();
});

it('admin can create a partner', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.store'), [
            'name' => 'Test Agency',
            'email' => 'agency@example.com',
            'phone' => '9876543210',
            'notes' => 'Our primary content partner.',
        ])
        ->assertRedirect(route('partners.index'));

    expect(Partner::where('name', 'Test Agency')->exists())->toBeTrue();
});

it('manager can update a partner', function () {
    $partner = Partner::factory()->create();

    actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->put(route('partners.update', $partner), [
            'name' => 'Updated Agency',
            'email' => null,
            'phone' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('partners.index'));

    expect($partner->fresh()->name)->toBe('Updated Agency');
});

it('admin can delete a partner', function () {
    $partner = Partner::factory()->create();

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->delete(route('partners.destroy', $partner))
        ->assertRedirect(route('partners.index'));

    expect(Partner::find($partner->id))->toBeNull();
});

it('support cannot create a partner', function () {
    actingAs(User::factory()->create(['role' => UserRole::Support]))
        ->post(route('partners.store'), ['name' => 'Sneaky Agency'])
        ->assertForbidden();
});

it('admin can view a partner\'s client-health page, showing only that partner\'s overdue clients', function () {
    $partner = Partner::factory()->create();
    $otherPartner = Partner::factory()->create();

    $theirClient = Customer::factory()->create(['company_name' => 'Referred Co', 'referring_partner_id' => $partner->id]);
    Invoice::factory()->create([
        'customer_id' => $theirClient->id, 'status' => InvoiceStatus::Overdue,
        'recurring_invoice_id' => \App\Models\RecurringInvoice::factory()->create(['customer_id' => $theirClient->id])->id,
        'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 0,
    ]);

    $otherClient = Customer::factory()->create(['company_name' => 'Not Referred Co', 'referring_partner_id' => $otherPartner->id]);
    Invoice::factory()->create([
        'customer_id' => $otherClient->id, 'status' => InvoiceStatus::Overdue,
        'recurring_invoice_id' => \App\Models\RecurringInvoice::factory()->create(['customer_id' => $otherClient->id])->id,
        'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 0,
    ]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee('Referred Co')
        ->assertDontSee('Not Referred Co');
});

it('shows client-wise billing for the last 6 months on the partner show page', function () {
    $partner = Partner::factory()->create();
    $billedClient = Customer::factory()->create(['company_name' => 'Billed Recently Co', 'referring_partner_id' => $partner->id]);
    $unbilledClient = Customer::factory()->create(['company_name' => 'Never Billed Co', 'referring_partner_id' => $partner->id]);
    Invoice::factory()->create([
        'customer_id' => $billedClient->id, 'status' => InvoiceStatus::Paid,
        'issue_date' => now()->subMonths(2), 'total' => 100000, 'amount_paid' => 100000,
    ]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSeeInOrder(['Billed — last 6 months', 'Billed Recently Co', 'Never Billed Co']);
});

it('shows a month-wise billed breakdown for the last 6 months on the partner show page', function () {
    $partner = Partner::factory()->create();
    $customer = Customer::factory()->create(['referring_partner_id' => $partner->id]);
    Invoice::factory()->create([
        'customer_id' => $customer->id, 'status' => InvoiceStatus::Paid,
        'issue_date' => now()->startOfMonth(), 'total' => 100000, 'amount_paid' => 100000,
    ]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee(now()->format('M Y'));
});

it('sales cannot view a partner show page', function () {
    $partner = Partner::factory()->create();

    actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->get(route('partners.show', $partner))
        ->assertForbidden();
});
