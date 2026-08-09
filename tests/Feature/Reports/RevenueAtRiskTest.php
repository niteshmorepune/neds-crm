<?php

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Services\RevenueAtRiskMetrics;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->metrics = app(RevenueAtRiskMetrics::class);
});

function riskTemplate(array $attributes = []): RecurringInvoice
{
    $template = RecurringInvoice::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create(['status' => CustomerStatus::Active])->id,
        'is_active' => true,
        'frequency' => RecurringFrequency::Monthly->value,
    ], $attributes));

    $template->items()->create([
        'description' => 'Monthly retainer', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18,
    ]);

    return $template->refresh();
}

it('lets admin and manager reach the revenue at risk page, but forbids other roles', function () {
    $this->seed(MenuItemsSeeder::class);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->get(route('revenue-at-risk.index'))->assertOk();
    $this->actingAs($manager)->get(route('revenue-at-risk.index'))->assertOk();
    $this->actingAs($sales)->get(route('revenue-at-risk.index'))->assertForbidden();
});

it('counts only overdue receivables toward the AR bucket, excluding not-yet-due invoices', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent->value, 'due_date' => now()->addDay(), 'total' => 100000, 'amount_paid' => 0]);
    Invoice::factory()->create(['status' => InvoiceStatus::Sent->value, 'due_date' => now()->subDays(10), 'total' => 200000, 'amount_paid' => 0]);

    $signal = $this->metrics->signals()->firstWhere('key', 'overdue_receivables');
    expect($signal['amount'])->toBe(200000);
});

it('computes renewals-due MRR using the same 30-day scope as the Contract Renewal Dashboard', function () {
    riskTemplate(['end_date' => now()->addDays(10)]);
    riskTemplate(['end_date' => now()->addDays(60)]);

    $signal = $this->metrics->signals()->firstWhere('key', 'renewals_due_mrr');
    expect($signal['amount'])->toBe(100000);
});

it('sums MRR only for clients Client Radar has actually flagged', function () {
    $flagged = Customer::factory()->create(['status' => CustomerStatus::Active]);
    Invoice::factory()->create(['customer_id' => $flagged->id, 'status' => InvoiceStatus::Overdue->value, 'total' => 50000, 'amount_paid' => 0]);
    riskTemplate(['customer_id' => $flagged->id]);

    $healthy = Customer::factory()->create(['status' => CustomerStatus::Active]);
    $healthy->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'Recent touch.']);
    riskTemplate(['customer_id' => $healthy->id]);

    $signal = $this->metrics->signals()->firstWhere('key', 'at_risk_client_mrr');
    expect($signal['amount'])->toBe(100000);
});

it('sums all three buckets into the total without deduplicating overlap between them', function () {
    // Overdue-invoice client is both an AR-aging entry AND a Client-Radar-flagged client with its own MRR.
    $overlapping = Customer::factory()->create(['status' => CustomerStatus::Active]);
    Invoice::factory()->create(['customer_id' => $overlapping->id, 'status' => InvoiceStatus::Overdue->value, 'due_date' => now()->subDays(5), 'total' => 300000, 'amount_paid' => 0]);
    riskTemplate(['customer_id' => $overlapping->id]);
    riskTemplate(['end_date' => now()->addDays(10)]);

    $expected = $this->metrics->signals()->sum('amount');
    expect($this->metrics->totalAtRisk())->toBe($expected)
        // The overdue invoice's own 300000 and the same client's 100000 MRR both count separately.
        ->and($expected)->toBeGreaterThanOrEqual(300000 + 100000);
});

it('links each bucket to its own existing report', function () {
    $signals = $this->metrics->signals()->keyBy('key');

    expect($signals['overdue_receivables']['route'])->toBe(route('reports.receivables'))
        ->and($signals['renewals_due_mrr']['route'])->toBe(route('contract-renewals.index'))
        ->and($signals['at_risk_client_mrr']['route'])->toBe(route('client-radar.index'));
});
