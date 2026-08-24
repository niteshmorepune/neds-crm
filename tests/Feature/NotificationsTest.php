<?php

use App\Enums\QuotationApprovalStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\DealWonNotification;
use App\Notifications\LeadEscalatedToManagerNotification;
use App\Notifications\MeetingInvitation;
use App\Notifications\NewInvoiceNotification;
use App\Notifications\QuotationNeedsApproval;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->role(UserRole::Admin)->create();
});

it('renders a meeting invitation notification without crashing (2026-08-11 regression: neither message nor url in its data)', function () {
    $meeting = Meeting::factory()->create(['duration_minutes' => 30]);
    $this->user->notify(new MeetingInvitation($meeting));

    $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Meeting invitation');
});

it('never crashes the notifications page on a notification shape with neither message, url, nor task_title', function () {
    $this->user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'SomeUnknownType',
        'data' => ['type' => 'something_new_and_unhandled'],
        'read_at' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertOk();
});

it('shows a clickable link for a notification pointing to a live invoice', function () {
    $invoice = Invoice::factory()->create();
    $this->user->notify(new NewInvoiceNotification($invoice));

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee(route('invoices.show', $invoice), false)
        ->assertDontSee('invoice deleted');
});

it('shows a graceful message instead of a dead link when the notification\'s invoice has since been deleted', function () {
    $invoice = Invoice::factory()->create();
    $this->user->notify(new NewInvoiceNotification($invoice));
    $invoice->delete();

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('invoice deleted')
        ->assertDontSee(route('invoices.show', $invoice), false);
});

it('still links a notification whose invoice is live even when another notification on the same page points to a deleted invoice', function () {
    $liveInvoice = Invoice::factory()->create();
    $deletedInvoice = Invoice::factory()->create();
    $this->user->notify(new NewInvoiceNotification($liveInvoice));
    $this->user->notify(new NewInvoiceNotification($deletedInvoice));
    $deletedInvoice->delete();

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()->assertSee(route('invoices.show', $liveInvoice), false);
});

it('shows a graceful message instead of a dead link when the notification\'s deal has since been deleted', function () {
    // 2026-08-04 regression: same shape as the invoice dead-link fix above,
    // just never extended to Deal — a deal marked Won (firing
    // DealWonNotification) and then deleted left a bare 404 on click.
    $deal = Deal::factory()->create();
    $this->user->notify(new DealWonNotification($deal));
    $deal->delete();

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('deal deleted')
        ->assertDontSee(route('deals.show', $deal), false);
});

it('shows a clickable link for a deal-won notification pointing to a live deal', function () {
    $deal = Deal::factory()->create();
    $this->user->notify(new DealWonNotification($deal));

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee(route('deals.show', $deal), false)
        ->assertDontSee('deal deleted');
});

it('shows a graceful message instead of a dead link when the notification\'s lead has since been deleted', function () {
    // 2026-08-13 regression: same shape as the invoice/deal dead-link fixes
    // above, never extended to Lead — the new speed-to-lead escalation cron
    // (PR #118) notifies managers about an untouched lead, and if that lead
    // is deleted afterward (e.g. cleaned up as a duplicate/spam) the stored
    // link 404s on click.
    $lead = Lead::factory()->create();
    $this->user->notify(new LeadEscalatedToManagerNotification($lead));
    $lead->delete();

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('lead deleted')
        ->assertDontSee(route('leads.show', $lead), false);
});

it('shows a clickable link for a lead-escalated notification pointing to a live lead', function () {
    $lead = Lead::factory()->create();
    $this->user->notify(new LeadEscalatedToManagerNotification($lead));

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee(route('leads.show', $lead), false)
        ->assertDontSee('lead deleted');
});

it('shows a still-actionable link for a quotation-needs-approval notification while the quotation is still pending', function () {
    $quotation = Quotation::factory()->create(['approval_status' => QuotationApprovalStatus::Pending]);
    $this->user->notify(new QuotationNeedsApproval($quotation));

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee(route('quotations.show', $quotation), false)
        ->assertDontSee('approved by');
});

it('relabels a quotation-needs-approval notification once another admin/manager has already approved it (2026-08-24 regression)', function () {
    // Reported: Admin still saw "Quotation needs approval: NSS Business Group"
    // in their own notifications list after Manager Manali had already
    // approved it — approve()/reject()/requestChanges() never touched any
    // other recipient's copy of the notification, so it sat there forever
    // looking identically actionable to a genuinely-pending one.
    $approver = User::factory()->role(UserRole::Manager)->create(['name' => 'Manali Deshpande']);
    $quotation = Quotation::factory()->create(['approval_status' => QuotationApprovalStatus::Pending]);
    $this->user->notify(new QuotationNeedsApproval($quotation));

    $quotation->update([
        'approval_status' => QuotationApprovalStatus::Approved,
        'approved_by' => $approver->id,
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('approved by Manali Deshpande')
        ->assertDontSee(route('quotations.show', $quotation), false);
});

it('relabels a quotation-needs-approval notification once the quotation has been rejected, without an approver name', function () {
    $quotation = Quotation::factory()->create(['approval_status' => QuotationApprovalStatus::Pending]);
    $this->user->notify(new QuotationNeedsApproval($quotation));

    $quotation->update(['approval_status' => QuotationApprovalStatus::Rejected]);

    $response = $this->actingAs($this->user)->get(route('notifications.index'));

    $response->assertOk()->assertSee('(rejected)', false);
});
