<?php

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\NewInvoiceNotification;

beforeEach(function () {
    $this->user = User::factory()->role(UserRole::Admin)->create();
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
