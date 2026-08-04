<?php

use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\DealWonNotification;
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
