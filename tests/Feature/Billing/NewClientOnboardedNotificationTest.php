<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewClientOnboardedNotification;
use App\Notifications\PaymentRecordedNotification;
use App\Services\RazorpayPaymentRecorder;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->accounts = User::factory()->role(UserRole::Accounts)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->manager = User::factory()->role(UserRole::Manager)->create();
    $this->owner = User::factory()->role(UserRole::Sales)->create();
    $this->customer = Customer::factory()->create(['owner_id' => $this->owner->id, 'company_name' => 'Victory Seals']);
});

it('notifies Admin and Manager the first time a client ever gets a payment', function () {
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create([
        'customer_id' => $this->customer->id, 'total' => 100000,
    ]);

    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1000', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);

    expect($this->admin->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->exists())->toBeTrue();
    expect($this->manager->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->exists())->toBeTrue();

    $data = $this->admin->fresh()->notifications()->first()->data;
    expect($data['message'])->toContain('Victory Seals')
        ->and($data['url'])->toBe(route('clients.show', $this->customer))
        ->and($data)->not->toHaveKey('invoice_id');
});

it('does not notify again for a second payment on a client who already has one', function () {
    $first = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 100000]);
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $first), [
        'amount' => '1000', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);
    expect($this->admin->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->count())->toBe(1);

    $second = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 50000]);
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $second), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ]);

    expect($this->admin->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->count())->toBe(1);
});

it('does not fire for a second, partial payment completing the same invoice', function () {
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 100000]);

    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '600', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '400', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);

    expect($this->admin->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->count())->toBe(1);
});

it('lists the client\'s currently active services in the notification message', function () {
    $service = Service::factory()->create(['name' => 'Website Development']);
    Project::factory()->create(['customer_id' => $this->customer->id, 'service_id' => $service->id]);

    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 100000]);
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1000', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);

    $data = $this->admin->fresh()->notifications()->first()->data;
    expect($data['services'])->toBe(['Website Development'])
        ->and($data['message'])->toContain('Website Development');
});

it('fires the onboarding notification when an advance is applied as a client\'s first payment', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 5000000, 'received_on' => now(), 'mode' => PaymentMode::Upi, 'recorded_by' => $this->accounts->id,
    ]);
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 3000000]);

    $this->actingAs($this->accounts)->post(route('invoices.advances.apply', [$invoice, $advance]), ['amount' => 30000]);

    expect($this->admin->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->exists())->toBeTrue();
});

it('fires the onboarding notification on a client\'s first Razorpay-captured payment', function () {
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 100000]);

    app(RazorpayPaymentRecorder::class)->record($invoice, 'order_1', 'pay_1', 100000);

    expect($this->admin->fresh()->notifications()->where('type', NewClientOnboardedNotification::class)->exists())->toBeTrue();
});

it('still fires the routine PaymentRecordedNotification alongside the new onboarding one', function () {
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 100000]);

    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1000', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);

    expect($this->owner->fresh()->notifications()->where('type', PaymentRecordedNotification::class)->exists())->toBeTrue();
});
