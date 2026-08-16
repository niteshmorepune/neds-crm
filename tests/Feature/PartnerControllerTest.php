<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Quotation;
use App\Models\RecurringInvoice;
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

it('admin can set a partner as a reseller billed to a customer', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.store'), [
            'name' => 'Brand-Whiz',
            'billing_customer_id' => $billTo->id,
        ])
        ->assertRedirect(route('partners.index'));

    $partner = Partner::where('name', 'Brand-Whiz')->firstOrFail();
    expect($partner->billing_customer_id)->toBe($billTo->id)
        ->and($partner->billingCustomer->id)->toBe($billTo->id);
});

it('can clear a partner\'s reseller billing customer', function () {
    $billTo = Customer::factory()->create();
    $partner = Partner::factory()->create(['billing_customer_id' => $billTo->id]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->put(route('partners.update', $partner), [
            'name' => $partner->name,
            'billing_customer_id' => null,
        ])
        ->assertRedirect(route('partners.index'));

    expect($partner->fresh()->billing_customer_id)->toBeNull();
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
        'recurring_invoice_id' => RecurringInvoice::factory()->create(['customer_id' => $theirClient->id])->id,
        'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 0,
    ]);

    $otherClient = Customer::factory()->create(['company_name' => 'Not Referred Co', 'referring_partner_id' => $otherPartner->id]);
    Invoice::factory()->create([
        'customer_id' => $otherClient->id, 'status' => InvoiceStatus::Overdue,
        'recurring_invoice_id' => RecurringInvoice::factory()->create(['customer_id' => $otherClient->id])->id,
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

it('shows quotations for a partner\'s referred clients, and excludes another partner\'s', function () {
    $partner = Partner::factory()->create();
    $otherPartner = Partner::factory()->create();

    $theirClient = Customer::factory()->create(['company_name' => 'Prajakta Referral Co', 'referring_partner_id' => $partner->id]);
    Quotation::factory()->create(['customer_id' => $theirClient->id, 'number' => 'QTN/2026-27/9001']);

    $otherClient = Customer::factory()->create(['company_name' => 'Unrelated Co', 'referring_partner_id' => $otherPartner->id]);
    Quotation::factory()->create(['customer_id' => $otherClient->id, 'number' => 'QTN/2026-27/9002']);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee('QTN/2026-27/9001')
        ->assertDontSee('QTN/2026-27/9002');
});

it('sales cannot view a partner show page', function () {
    $partner = Partner::factory()->create();

    actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->get(route('partners.show', $partner))
        ->assertForbidden();
});

it('shows a reseller partner\'s consolidated account, including quotations billed to their billing customer', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);
    $partner = Partner::factory()->create(['name' => 'Brand-Whiz', 'billing_customer_id' => $billTo->id]);
    $referred = Customer::factory()->create(['company_name' => 'Sub Client Co', 'referring_partner_id' => $partner->id]);

    // Reseller-billed: the invoice/quotation's customer_id is the billing
    // customer, NOT the referred client (Customer::billingTarget()).
    Invoice::factory()->create([
        'customer_id' => $billTo->id, 'status' => InvoiceStatus::Overdue,
        'invoice_number' => 'NEDS/2026-27/9101', 'due_date' => now()->subDays(5),
        'total' => 50000, 'amount_paid' => 0,
    ]);
    Quotation::factory()->create(['customer_id' => $billTo->id, 'number' => 'QTN/2026-27/9101']);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee('Your Account')
        ->assertSee('NEDS/2026-27/9101')
        ->assertSee('QTN/2026-27/9101');
});

it('does not show a "Your Account" section for a non-reseller partner', function () {
    $partner = Partner::factory()->create(['billing_customer_id' => null]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertDontSee('Your Account');
});
