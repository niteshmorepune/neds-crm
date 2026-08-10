<?php

use App\Enums\QuotationStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationDecisionRecorded;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->customer = Customer::factory()->create(['owner_id' => $this->owner->id]);
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
});

it('lets a client accept a sent quotation', function () {
    $quotation = Quotation::factory()->status(QuotationStatus::Sent)->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.quotations.accept', $quotation))
        ->assertRedirect(route('portal.quotations.show', $quotation));

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Accepted);
    expect($this->owner->fresh()->notifications()->where('type', QuotationDecisionRecorded::class)->exists())->toBeTrue();
});

it('lets a client reject a sent quotation with an optional note', function () {
    $quotation = Quotation::factory()->status(QuotationStatus::Sent)->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.quotations.reject', $quotation), ['client_decision_note' => 'Too expensive for now.'])
        ->assertRedirect(route('portal.quotations.show', $quotation));

    $quotation->refresh();
    expect($quotation->status)->toBe(QuotationStatus::Rejected);
    expect($quotation->client_decision_note)->toBe('Too expensive for now.');
});

it('blocks accepting a quotation that is not Sent', function () {
    $quotation = Quotation::factory()->status(QuotationStatus::Draft)->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.quotations.accept', $quotation))
        ->assertSessionHasErrors('status');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('blocks deciding on an already-decided quotation', function () {
    $quotation = Quotation::factory()->status(QuotationStatus::Accepted)->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.quotations.reject', $quotation))
        ->assertSessionHasErrors('status');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Accepted);
});

it('404s on another customer\'s quotation', function () {
    $otherCustomer = Customer::factory()->create();
    $theirs = Quotation::factory()->status(QuotationStatus::Sent)->create(['customer_id' => $otherCustomer->id]);

    $this->actingAs($this->contact, 'portal')
        ->get(route('portal.quotations.show', $theirs))
        ->assertNotFound();

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.quotations.accept', $theirs))
        ->assertNotFound();
});
