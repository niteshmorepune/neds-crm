<?php

use App\Enums\InvoiceStatus;
use App\Mail\PaymentReminder;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

function dueInvoice(Carbon $dueDate, array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->create(array_merge([
        'status' => InvoiceStatus::Sent, 'due_date' => $dueDate->toDateString(), 'place_of_supply_state_code' => '27',
    ], $attributes));
    $invoice->items()->create([
        'description' => 'Service', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000,
    ]);
    $invoice->refresh()->recalculateTotals();

    return $invoice->refresh();
}

it('sends reminders 3 days before, on the due date, and every 7 days after', function () {
    Mail::fake();
    $today = Carbon::today();

    dueInvoice($today->copy()->addDays(3)); // 3 days before -> yes
    dueInvoice($today->copy());             // on due date -> yes
    dueInvoice($today->copy()->subDays(7)); // 7 days after -> yes
    dueInvoice($today->copy()->addDays(5)); // no
    dueInvoice($today->copy()->subDays(5)); // no (5 % 7 != 0)

    $this->artisan('app:send-payment-reminders')->assertSuccessful();

    Mail::assertSent(PaymentReminder::class, 3);
});

it('does not remind on fully paid invoices', function () {
    Mail::fake();
    $invoice = dueInvoice(Carbon::today());
    $invoice->update(['amount_paid' => $invoice->total, 'status' => InvoiceStatus::Paid]);

    $this->artisan('app:send-payment-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

it('marks past-due unpaid invoices as overdue', function () {
    $overdue = dueInvoice(Carbon::today()->subDay());       // sent, unpaid, past due
    $future = dueInvoice(Carbon::today()->addDay());        // not due
    $paid = dueInvoice(Carbon::today()->subDay());
    $paid->update(['amount_paid' => $paid->total, 'status' => InvoiceStatus::Paid]);

    $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($future->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($paid->fresh()->status)->toBe(InvoiceStatus::Paid);
});
