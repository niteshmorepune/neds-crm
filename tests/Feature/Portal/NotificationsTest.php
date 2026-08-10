<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuotationApprovalStatus;
use App\Enums\UserRole;
use App\Livewire\RecordNotes;
use App\Livewire\TicketReplies;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\InvoiceSentNotification;
use App\Notifications\ProjectUpdatePosted;
use App\Notifications\QuotationAwaitingDecision;
use App\Notifications\TicketReplyPosted;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->seed(MenuItemsSeeder::class);
    $this->staff = User::factory()->create(['role' => UserRole::Admin]);
    $this->customer = Customer::factory()->create(['owner_id' => $this->staff->id]);
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
});

it('notifies portal contacts when a quotation is sent', function () {
    $quotation = Quotation::factory()->create([
        'customer_id' => $this->customer->id,
        'approval_status' => QuotationApprovalStatus::Approved,
    ]);
    $quotation->items()->create(['description' => 'Service', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000]);

    $this->actingAs($this->staff)->post(route('quotations.send', $quotation))->assertRedirect();

    expect($this->contact->fresh()->notifications()->where('type', QuotationAwaitingDecision::class)->exists())->toBeTrue();
});

it('notifies portal contacts when an invoice is sent', function () {
    $invoice = Invoice::factory()->status(InvoiceStatus::Draft)->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->staff)->post(route('invoices.send', $invoice))->assertRedirect();

    expect($this->contact->fresh()->notifications()->where('type', InvoiceSentNotification::class)->exists())->toBeTrue();
});

it('notifies portal contacts on a non-internal ticket reply, not an internal one', function () {
    $ticket = Ticket::factory()->create(['customer_id' => $this->customer->id]);

    Livewire::actingAs($this->staff)->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->set('body', 'We are on it.')
        ->set('is_internal', false)
        ->call('addReply');

    expect($this->contact->fresh()->notifications()->where('type', TicketReplyPosted::class)->exists())->toBeTrue();

    Livewire::actingAs($this->staff)->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->set('body', 'Internal note only.')
        ->set('is_internal', true)
        ->call('addReply');

    expect($this->contact->fresh()->notifications()->where('type', TicketReplyPosted::class)->count())->toBe(1);
});

it('notifies portal contacts on a client-visible project note, not a private one', function () {
    $project = Project::factory()->create(['customer_id' => $this->customer->id]);

    Livewire::actingAs($this->staff)->test(RecordNotes::class, ['record' => $project, 'canManage' => true, 'showPortalToggle' => true])
        ->set('body', 'Milestone 1 delivered.')
        ->set('visibleToClient', true)
        ->call('addNote');

    expect($this->contact->fresh()->notifications()->where('type', ProjectUpdatePosted::class)->exists())->toBeTrue();

    Livewire::actingAs($this->staff)->test(RecordNotes::class, ['record' => $project, 'canManage' => true, 'showPortalToggle' => true])
        ->set('body', 'Internal-only remark.')
        ->set('visibleToClient', false)
        ->call('addNote');

    expect($this->contact->fresh()->notifications()->where('type', ProjectUpdatePosted::class)->count())->toBe(1);
});

it('does not notify on notes for non-project records', function () {
    $lead = Lead::factory()->create();

    Livewire::actingAs($this->staff)->test(RecordNotes::class, ['record' => $lead, 'canManage' => true, 'showPortalToggle' => true])
        ->set('body', 'Called, no answer.')
        ->set('visibleToClient', true)
        ->call('addNote');

    expect($this->contact->fresh()->notifications()->where('type', ProjectUpdatePosted::class)->exists())->toBeFalse();
});

it('marks notifications read on visiting the notifications page and can dismiss one', function () {
    $quotation = Quotation::factory()->create(['customer_id' => $this->customer->id]);
    $this->contact->notify(new QuotationAwaitingDecision($quotation));

    $this->actingAs($this->contact, 'portal');

    expect($this->contact->unreadNotifications()->count())->toBe(1);

    $this->get(route('portal.notifications.index'))->assertOk();
    expect($this->contact->fresh()->unreadNotifications()->count())->toBe(0);

    $notification = $this->contact->fresh()->notifications()->first();
    $this->delete(route('portal.notifications.destroy', $notification->id))->assertRedirect();
    expect($this->contact->fresh()->notifications()->count())->toBe(0);
});
