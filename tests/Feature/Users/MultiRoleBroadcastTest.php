<?php

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mail\SlaEscalation;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\DealWonNotification;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\NewLeadNotification;
use App\Notifications\PaymentRecordedNotification;
use App\Notifications\RecurringInvoiceDueSoon;
use App\Notifications\SmdostBriefApproved;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Every broadcast/eligibility/owner-dropdown call site converted to
 * User::withAnyRole() as part of multi-role support. Each test creates a user
 * whose PRIMARY role would not normally qualify, but who has been granted the
 * qualifying role as an ADDITIONAL role, and asserts they're now included —
 * the single-role case for each of these is already covered by its own
 * feature's existing tests and is untouched by this change.
 */
beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('deal won: notifies a secondary admin/manager', function () {
    Notification::fake();
    $secondaryManager = User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Manager)->create();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    $deal->update(['stage' => DealStage::Won]);

    Notification::assertSentTo($secondaryManager, DealWonNotification::class);
});

it('SLA escalation: emails a secondary admin/manager', function () {
    Mail::fake();
    $secondaryManager = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Manager)->create();
    $breached = Ticket::factory()->breached()->create();

    expect($breached->isSlaBreached())->toBeTrue();

    $this->artisan('app:check-ticket-sla')->assertSuccessful();

    Mail::assertSent(SlaEscalation::class, fn (SlaEscalation $m) => $m->hasTo($secondaryManager->email));
});

it('recurring invoice due-soon: notifies a secondary accounts/admin/manager', function () {
    Notification::fake();
    $secondaryAccounts = User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    $recurring = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'recurring_invoice_id' => $recurring->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => now()->timezone('Asia/Kolkata')->addDays(7),
    ]);

    $this->artisan('app:send-recurring-invoice-due-warnings')->assertSuccessful();

    Notification::assertSentTo($secondaryAccounts, RecurringInvoiceDueSoon::class, fn ($n) => $n->invoice->is($invoice));
});

it('SMDost brief approved: notifies a secondary accounts/admin', function () {
    Notification::fake();
    config(['services.smdost.service_key' => 'test-smdost-secret']);
    $secondaryAccounts = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Accounts)->create();
    Customer::factory()->create(['smdost_client_id' => 'smd-client-multirole']);

    $this->withToken('test-smdost-secret')->postJson('/api/webhooks/smdost/brief-approved', [
        'smdost_client_id' => 'smd-client-multirole',
        'brief_id' => 'brief-multirole-1',
        'brief_title' => 'Multi-role test campaign',
        'scheduled_month' => now()->format('Y-m'),
        'post_count' => 4,
    ])->assertOk();

    Notification::assertSentTo($secondaryAccounts, SmdostBriefApproved::class);
});

it('payment recorded: notifies a secondary accounts user', function () {
    Notification::fake();
    $accountsPrimary = User::factory()->role(UserRole::Accounts)->create();
    $secondaryAccounts = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Accounts)->create();
    $invoice = Invoice::factory()->create(['place_of_supply_state_code' => '27']);
    $invoice->items()->create([
        'description' => 'SEO retainer', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000,
    ]);
    $invoice->refresh()->recalculateTotals();

    $this->actingAs($accountsPrimary)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1180', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ])->assertRedirect();

    Notification::assertSentTo($secondaryAccounts, PaymentRecordedNotification::class);
});

it('leave request submitted: notifies a secondary admin/manager', function () {
    Notification::fake();
    $employee = User::factory()->role(UserRole::Sales)->create();
    $secondaryManager = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Admin)->create();

    $start = now()->addWeek()->startOfWeek();
    $this->actingAs($employee)->post(route('leave-requests.store'), [
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDay()->toDateString(),
        'reason' => 'Family function',
    ])->assertRedirect();

    Notification::assertSentTo($secondaryManager, LeaveRequestSubmitted::class);
});

it('unowned new lead: notifies a secondary Sales user', function () {
    Notification::fake();
    // No active primary-Sales user exists, so the lead stays unowned and the
    // fallback broadcast (not the single-assignee autoAssign query) fires.
    $secondarySales = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBeNull();
    Notification::assertSentTo($secondarySales, NewLeadNotification::class);
});

it('client owner dropdown: includes a secondary Sales/Manager/Admin user', function () {
    $secondarySales = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create(['name' => 'Secondary Sales Person']);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('clients.create'))->assertOk()->assertSee('Secondary Sales Person');
});

it('lead owner dropdown: includes a secondary Sales/Manager/Admin user', function () {
    $secondarySales = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create(['name' => 'Secondary Sales Person Two']);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('leads.create'))->assertOk()->assertSee('Secondary Sales Person Two');
});
