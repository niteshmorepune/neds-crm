<?php

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;

beforeEach(function () {
    $this->customerA = Customer::factory()->create(['company_name' => 'Alpha Corp']);
    $this->contactA = Contact::factory()->portalUser()->create(['customer_id' => $this->customerA->id]);

    $this->customerB = Customer::factory()->create(['company_name' => 'Bravo Corp']);
});

it('shows the contact only their own company profile', function () {
    $this->actingAs($this->contactA, 'portal')
        ->get(route('portal.home'))->assertOk()->assertSee('Alpha Corp')->assertDontSee('Bravo Corp');
});

it('isolates invoices to the contact\'s own customer', function () {
    $mine = Invoice::factory()->create(['customer_id' => $this->customerA->id]);
    $theirs = Invoice::factory()->create(['customer_id' => $this->customerB->id]);

    $this->actingAs($this->contactA, 'portal');

    $this->get(route('portal.invoices.index'))->assertOk()
        ->assertSee($mine->invoice_number)->assertDontSee($theirs->invoice_number);

    $this->get(route('portal.invoices.show', $mine->id))->assertOk();
    // The security guarantee: another customer's invoice is unreachable.
    $this->get(route('portal.invoices.show', $theirs->id))->assertNotFound();
    $this->get(route('portal.invoices.pdf', $theirs->id))->assertNotFound();
});

it('isolates projects to the contact\'s own customer', function () {
    $mine = Project::factory()->create(['customer_id' => $this->customerA->id]);
    $theirs = Project::factory()->create(['customer_id' => $this->customerB->id]);

    $this->actingAs($this->contactA, 'portal');

    $this->get(route('portal.projects.index'))->assertOk()
        ->assertSee($mine->name)->assertDontSee($theirs->name);

    $this->get(route('portal.projects.show', $mine->id))->assertOk();
    $this->get(route('portal.projects.show', $theirs->id))->assertNotFound();
});

it('can download its own invoice PDF', function () {
    $mine = Invoice::factory()->create(['customer_id' => $this->customerA->id]);
    $mine->items()->create(['description' => 'Service', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000]);

    $response = $this->actingAs($this->contactA, 'portal')->get(route('portal.invoices.pdf', $mine->id));

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF');
});

it('does not let an internal user session into the portal', function () {
    // A web (User) session must not satisfy the portal guard.
    $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
});
