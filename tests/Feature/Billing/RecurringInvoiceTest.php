<?php

use App\Mail\InvoiceIssued;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
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

it('skips templates that are not yet due', function () {
    Mail::fake();
    recurringWithLine(['next_run_on' => now()->addWeek()->toDateString()]);

    $this->artisan('app:generate-recurring-invoices')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
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
