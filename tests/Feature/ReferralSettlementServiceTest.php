<?php

use App\Enums\InvoiceStatus;
use App\Enums\PartnerCollectionMode;
use App\Enums\ReferralShareType;
use App\Enums\SettlementAmountSource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use App\Models\Service;
use App\Models\User;
use App\Services\ReferralSettlementService;

beforeEach(function () {
    $this->service = app(ReferralSettlementService::class);
    $this->partner = Partner::factory()->create();
    $this->seo = Service::factory()->create(['name' => 'SEO']);
});

it('sums real invoice totals for a template within the given month', function () {
    $customer = Customer::factory()->create(['referring_partner_id' => $this->partner->id]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $this->seo->id]);

    Invoice::factory()->create(['recurring_invoice_id' => $template->id, 'customer_id' => $customer->id, 'issue_date' => now()->startOfMonth()->addDays(2), 'total' => 300000]);
    Invoice::factory()->create(['recurring_invoice_id' => $template->id, 'customer_id' => $customer->id, 'issue_date' => now()->subMonth(), 'total' => 999999]);

    expect($this->service->billedAmountFromInvoices($template, now()->startOfMonth()))->toBe(300000);
});

it('marks a NedsCollects month paid or pending based on the real invoice status', function () {
    $customer = Customer::factory()->create(['referring_partner_id' => $this->partner->id]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $this->seo->id, 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->status(InvoiceStatus::Paid)->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $customer->id,
        'issue_date' => now()->startOfMonth(), 'total' => 300000,
    ]);

    $customer->load(['recurringInvoices.service', 'recurringInvoices.items', 'recurringInvoices.invoices', 'referralSettlements']);
    $grid = $this->service->gridForClient($customer);

    $currentMonthRow = collect($grid[0]['rows'])->firstWhere('period', now()->format('Y-m'));
    expect($currentMonthRow['billing_status'])->toBe('paid')
        ->and($currentMonthRow['amount'])->toBe(300000);
});

it('includes a reseller-billed template in a referred client\'s own settlement grid, found via project_id', function () {
    $billTo = Customer::factory()->create();
    $reseller = Partner::factory()->create(['billing_customer_id' => $billTo->id]);
    $customer = Customer::factory()->create(['referring_partner_id' => $reseller->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $template = RecurringInvoice::factory()->create([
        'customer_id' => $billTo->id, 'project_id' => $project->id,
        'service_id' => $this->seo->id, 'start_date' => now()->subMonths(2),
    ]);
    Invoice::factory()->status(InvoiceStatus::Paid)->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $billTo->id,
        'issue_date' => now()->startOfMonth(), 'total' => 300000,
    ]);

    $customer->load(['recurringInvoices.service', 'recurringInvoices.items', 'recurringInvoices.invoices', 'referralSettlements']);
    $grid = $this->service->gridForClient($customer);

    expect($grid)->toHaveCount(1);
    $currentMonthRow = collect($grid[0]['rows'])->firstWhere('period', now()->format('Y-m'));
    expect($currentMonthRow['billing_status'])->toBe('paid')
        ->and($currentMonthRow['amount'])->toBe(300000);
});

it('does not duplicate a client\'s own (non-reseller) template via the project_id bridge', function () {
    $customer = Customer::factory()->create(['referring_partner_id' => $this->partner->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'project_id' => $project->id, 'service_id' => $this->seo->id]);

    $customer->load(['recurringInvoices.service', 'recurringInvoices.items', 'recurringInvoices.invoices', 'referralSettlements']);
    $grid = $this->service->gridForClient($customer);

    expect($grid)->toHaveCount(1);
});

it('marks a future month upcoming with no amount', function () {
    $customer = Customer::factory()->create(['referring_partner_id' => $this->partner->id]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $this->seo->id, 'start_date' => now()->subMonths(2)]);

    $customer->load(['recurringInvoices.service', 'recurringInvoices.items', 'recurringInvoices.invoices', 'referralSettlements']);
    $grid = $this->service->gridForClient($customer);

    $futureRow = collect($grid[0]['rows'])->last();
    expect($futureRow['period'])->toBe(now()->addMonth()->format('Y-m'))
        ->and($futureRow['billing_status'])->toBe('upcoming')
        ->and($futureRow['amount'])->toBeNull();
});

it('marks a PartnerCollects month as collected only once a manual settlement is entered', function () {
    $customer = Customer::factory()->create([
        'referring_partner_id' => $this->partner->id,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects,
        'referral_share_rate' => 20,
    ]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $this->seo->id, 'start_date' => now()->subMonths(2)]);

    $customer->load(['recurringInvoices.service', 'recurringInvoices.items', 'recurringInvoices.invoices', 'referralSettlements']);
    $before = collect($this->service->gridForClient($customer)[0]['rows'])->firstWhere('period', now()->format('Y-m'));
    expect($before['billing_status'])->toBe('none');

    $user = User::factory()->create();
    $this->service->recordManualBilling($template, now()->startOfMonth(), 300000, $user);

    $customer->load('referralSettlements');
    $after = collect($this->service->gridForClient($customer)[0]['rows'])->firstWhere('period', now()->format('Y-m'));
    expect($after['billing_status'])->toBe('collected')
        ->and($after['amount'])->toBe(300000);
});

it('computes share_amount as billed_amount times the client\'s referral_share_rate', function () {
    $customer = Customer::factory()->create([
        'referring_partner_id' => $this->partner->id,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects,
        'referral_share_rate' => 25,
    ]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $this->seo->id]);
    $user = User::factory()->create();

    $settlement = $this->service->recordManualBilling($template, now()->startOfMonth(), 400000, $user);

    expect($settlement->billed_amount)->toBe(400000)
        ->and((float) $settlement->share_rate)->toBe(25.0)
        ->and($settlement->share_amount)->toBe(100000)
        ->and($settlement->flow_mode)->toBe(PartnerCollectionMode::PartnerCollects)
        ->and($settlement->amount_source)->toBe(SettlementAmountSource::Manual)
        ->and($settlement->entered_by)->toBe($user->id);
});

it('uses the fixed amount as-is, ignoring the billed amount, for a FixedAmount client', function () {
    // Real production scenario: Ampra Biobact is billed ~15,000+GST but the
    // referral share is a flat 10,000 regardless — never a percentage of
    // whatever gets billed.
    $customer = Customer::factory()->create([
        'referring_partner_id' => $this->partner->id,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects,
        'referral_share_type' => ReferralShareType::FixedAmount,
        'referral_share_fixed_amount' => 1000000, // ₹10,000
        'referral_share_rate' => null,
    ]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $this->seo->id]);
    $user = User::factory()->create();

    $settlement = $this->service->recordManualBilling($template, now()->startOfMonth(), 1500000, $user);

    expect($settlement->billed_amount)->toBe(1500000)
        ->and($settlement->share_amount)->toBe(1000000)
        ->and((float) $settlement->share_rate)->toBe(0.0);

    // A different billed amount next entry still yields the SAME fixed share.
    $settlement2 = $this->service->recordManualBilling($template, now()->addMonth()->startOfMonth(), 2000000, $user);
    expect($settlement2->share_amount)->toBe(1000000);
});

it('includes a FixedAmount NedsCollects customer in finalize eligibility even with no referral_share_rate set', function () {
    $customer = Customer::factory()->create([
        'referring_partner_id' => $this->partner->id,
        'referral_share_type' => ReferralShareType::FixedAmount,
        'referral_share_fixed_amount' => 1000000,
        'referral_share_rate' => null,
    ]);

    expect($customer->hasReferralShareConfigured())->toBeTrue()
        ->and($customer->referralShareAmount(1500000))->toBe(1000000);
});

it('updates the same month\'s row instead of creating a duplicate on re-entry', function () {
    $customer = Customer::factory()->create(['referring_partner_id' => $this->partner->id, 'referral_share_rate' => 20]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);
    $user = User::factory()->create();

    $this->service->recordManualBilling($template, now()->startOfMonth(), 100000, $user);
    $this->service->recordManualBilling($template, now()->startOfMonth(), 200000, $user);

    expect(ReferralSettlement::where('recurring_invoice_id', $template->id)->count())->toBe(1);
    expect(ReferralSettlement::first()->billed_amount)->toBe(200000);
});

it('settle stamps settled_at and settled_by', function () {
    $settlement = ReferralSettlement::factory()->create();
    $user = User::factory()->create();

    $this->service->settle($settlement, $user);

    expect($settlement->fresh()->isSettled())->toBeTrue()
        ->and($settlement->fresh()->settled_by)->toBe($user->id);
});

it('sums the net position per direction, excluding already-settled rows', function () {
    ReferralSettlement::factory()->create(['partner_id' => $this->partner->id, 'flow_mode' => PartnerCollectionMode::NedsCollects, 'share_amount' => 5000]);
    ReferralSettlement::factory()->settled()->create(['partner_id' => $this->partner->id, 'flow_mode' => PartnerCollectionMode::NedsCollects, 'share_amount' => 999999]);
    ReferralSettlement::factory()->partnerCollects()->create(['partner_id' => $this->partner->id, 'flow_mode' => PartnerCollectionMode::PartnerCollects, 'share_amount' => 3000]);

    $position = $this->service->portfolioNetPosition($this->partner->fresh()->referralSettlements);

    expect($position)->toBe(['neds_owes_partner' => 5000, 'partner_owes_neds' => 3000]);
});
