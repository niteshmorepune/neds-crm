<?php

use App\Enums\UserRole;
use App\Mail\InvoiceIssued;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function recurringWithLine(array $attributes = []): RecurringInvoice
{
    $template = RecurringInvoice::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create(['state_code' => '27', 'email' => 'client@x.test'])->id,
    ], $attributes));

    $template->items()->create([
        'description' => 'Monthly SEO retainer', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18,
    ]);

    return $template->refresh();
}

it('generates an invoice for a due template, emails it, and advances the schedule', function () {
    Mail::fake();
    $template = recurringWithLine(['next_run_on' => now()->subDay()->toDateString()]);

    $this->artisan('app:generate-recurring-invoices')->assertSuccessful();

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->total)->toBe(118000)
        ->and($invoice->customer_id)->toBe($template->customer_id);

    Mail::assertSent(InvoiceIssued::class, fn (InvoiceIssued $m) => $m->hasTo('client@x.test'));

    expect($template->fresh()->next_run_on->greaterThan(now()))->toBeTrue();
});

it('carries the customer\'s GST-exempt default onto a generated recurring invoice', function () {
    Mail::fake();
    $exemptCustomer = Customer::factory()->create(['state_code' => '27', 'email' => 'exempt@x.test', 'gst_exempt' => true]);
    recurringWithLine(['customer_id' => $exemptCustomer->id, 'next_run_on' => now()->subDay()->toDateString()]);

    $this->artisan('app:generate-recurring-invoices')->assertSuccessful();

    $invoice = Invoice::first();
    expect($invoice->is_gst_exempt)->toBeTrue()
        ->and($invoice->cgst_total)->toBe(0)
        ->and($invoice->total)->toBe(100000);
});

it('skips templates that are not yet due', function () {
    Mail::fake();
    recurringWithLine(['next_run_on' => now()->addWeek()->toDateString()]);

    $this->artisan('app:generate-recurring-invoices')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

it('generates via the "Generate & Send Now" button, self-healing a drifted invoice-number counter', function () {
    $this->seed(\Database\Seeders\MenuItemsSeeder::class);
    Mail::fake();

    // Reproduces the production bug: a manually-logged invoice landed ahead of
    // the invoice_number_sequences counter, so the counter is stuck behind the
    // real max and a naive generate() call would collide on every attempt.
    $manuallyLogged = Invoice::factory()->create();
    $manuallyLogged->update(['invoice_number' => 'NEDS/'.$manuallyLogged->financial_year.'/0050']);

    $template = recurringWithLine(['next_run_on' => now()->addWeek()->toDateString()]);
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $response = $this->actingAs($accounts)->post(route('recurring-invoices.generate-now', $template));

    $response->assertRedirect(route('recurring-invoices.show', $template));
    $invoice = Invoice::where('recurring_invoice_id', $template->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->invoice_number)->not->toBe($manuallyLogged->invoice_number);

    Mail::assertSent(InvoiceIssued::class);
});

it('deactivates a template once it passes its end date', function () {
    Mail::fake();
    $template = recurringWithLine([
        'next_run_on' => now()->subDay()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    $this->artisan('app:generate-recurring-invoices')->assertSuccessful();

    expect($template->fresh()->is_active)->toBeFalse();
});
