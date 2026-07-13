<?php

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\User;
use App\Services\BusinessOverviewMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(BusinessOverviewMetrics::class);
});

// --- Partner performance ---------------------------------------------------

it('counts customers referred via referring_partner_id independent of deal-level partner attribution', function () {
    $partner = Partner::factory()->create();
    $otherPartner = Partner::factory()->create();
    Customer::factory()->create(['referring_partner_id' => $partner->id]);
    $customer = Customer::factory()->create(); // no referring partner
    Deal::factory()->create(['customer_id' => $customer->id, 'partner_id' => $otherPartner->id, 'stage' => DealStage::Won]);

    $rows = $this->metrics->partnerPerformance();

    expect($rows->firstWhere('partner_id', $partner->id)['customers_referred'])->toBe(1)
        ->and($rows->firstWhere('partner_id', $otherPartner->id)['customers_referred'])->toBe(0)
        ->and($rows->firstWhere('partner_id', $otherPartner->id)['deals_won_count'])->toBe(1);
});

it('splits a partner referred customers into active/inactive/prospect counts', function () {
    $partner = Partner::factory()->create();
    Customer::factory()->create(['referring_partner_id' => $partner->id, 'status' => CustomerStatus::Active]);
    Customer::factory()->create(['referring_partner_id' => $partner->id, 'status' => CustomerStatus::Inactive]);
    Customer::factory()->create(['referring_partner_id' => $partner->id, 'status' => CustomerStatus::Prospect]);

    $row = $this->metrics->partnerPerformance()->firstWhere('partner_id', $partner->id);

    expect($row['customers_active'])->toBe(1)
        ->and($row['customers_inactive'])->toBe(1)
        ->and($row['customers_prospect'])->toBe(1);
});

it('buckets a partner attributed deals into won/pipeline/lost counts and values', function () {
    $partner = Partner::factory()->create();
    Deal::factory()->create(['partner_id' => $partner->id, 'stage' => DealStage::Won, 'value' => 500000]);
    Deal::factory()->create(['partner_id' => $partner->id, 'stage' => DealStage::Proposal, 'value' => 200000]);
    Deal::factory()->create(['partner_id' => $partner->id, 'stage' => DealStage::Lost, 'value' => 100000]);

    $row = $this->metrics->partnerPerformance()->firstWhere('partner_id', $partner->id);

    expect($row['deals_won_count'])->toBe(1)->and($row['deals_won_value'])->toBe(500000)
        ->and($row['deals_pipeline_count'])->toBe(1)->and($row['deals_pipeline_value'])->toBe(200000)
        ->and($row['deals_lost_count'])->toBe(1)->and($row['deals_lost_value'])->toBe(100000);
});

it('excludes a partner with zero referred customers and zero attributed deals', function () {
    Partner::factory()->create();

    expect($this->metrics->partnerPerformance())->toBeEmpty();
});

it('sorts partners by won value descending', function () {
    $small = Partner::factory()->create();
    $big = Partner::factory()->create();
    Deal::factory()->create(['partner_id' => $small->id, 'stage' => DealStage::Won, 'value' => 100000]);
    Deal::factory()->create(['partner_id' => $big->id, 'stage' => DealStage::Won, 'value' => 900000]);

    $rows = $this->metrics->partnerPerformance();

    expect($rows->first()['partner_id'])->toBe($big->id);
});

// --- AR aging ----------------------------------------------------------------

it('buckets an invoice due today as current with zero days overdue', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now(), 'total' => 100000, 'amount_paid' => 0]);

    $row = collect($this->metrics->arAging()['invoices'])->first();

    expect($row['bucket'])->toBe('current')->and($row['days_overdue'])->toBeLessThanOrEqual(0);
});

it('buckets an invoice 45 days overdue into 31_60', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now()->subDays(45), 'total' => 100000, 'amount_paid' => 0]);

    $row = collect($this->metrics->arAging()['invoices'])->first();

    expect($row['bucket'])->toBe('31_60');
});

it('buckets exactly 90 and 91 days overdue into 61_90 and 90_plus respectively', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now()->subDays(90), 'total' => 100000, 'amount_paid' => 0]);
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now()->subDays(91), 'total' => 100000, 'amount_paid' => 0]);

    $buckets = collect($this->metrics->arAging()['invoices'])->pluck('bucket', 'days_overdue');

    expect($buckets[90])->toBe('61_90')->and($buckets[91])->toBe('90_plus');
});

it('excludes draft, cancelled and fully paid invoices from AR aging', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 0]);
    Invoice::factory()->create(['status' => InvoiceStatus::Cancelled, 'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 0]);
    Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 100000]);

    expect($this->metrics->arAging()['total_outstanding'])->toBe(0);
});

it('uses the remaining balance not the full total for a partially paid invoice', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::PartiallyPaid, 'due_date' => now()->subDays(10), 'total' => 100000, 'amount_paid' => 40000]);

    $row = collect($this->metrics->arAging()['invoices'])->first();

    expect($row['balance'])->toBe(60000);
});

it('sums AR bucket totals to the reported total outstanding', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now(), 'total' => 100000, 'amount_paid' => 0]);
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now()->subDays(45), 'total' => 200000, 'amount_paid' => 0]);

    $aging = $this->metrics->arAging();

    expect(collect($aging['buckets'])->sum('total'))->toBe($aging['total_outstanding']);
});

// --- MRR snapshot --------------------------------------------------------------

it('normalizes a quarterly recurring template to a third of its cycle amount', function () {
    $template = RecurringInvoice::factory()->create(['frequency' => RecurringFrequency::Quarterly, 'discount' => 0]);
    $template->items()->create(['description' => 'SEO', 'quantity' => 1, 'rate' => 300000, 'gst_rate' => 18, 'sort_order' => 1]);

    $row = collect($this->metrics->mrrSnapshot()['by_service'])->first();

    expect($row['monthly_equivalent'])->toBe(100000);
});

it('normalizes a yearly recurring template to a twelfth of its cycle amount', function () {
    $template = RecurringInvoice::factory()->create(['frequency' => RecurringFrequency::Yearly, 'discount' => 0]);
    $template->items()->create(['description' => 'AMC', 'quantity' => 1, 'rate' => 1200000, 'gst_rate' => 18, 'sort_order' => 1]);

    $row = collect($this->metrics->mrrSnapshot()['by_service'])->first();

    expect($row['monthly_equivalent'])->toBe(100000);
});

it('computes the cycle amount pre-GST, minus the template discount, floored at zero', function () {
    $template = RecurringInvoice::factory()->create(['frequency' => RecurringFrequency::Monthly, 'discount' => 150000]);
    $template->items()->create(['description' => 'Social Media', 'quantity' => 2, 'rate' => 50000, 'gst_rate' => 18, 'sort_order' => 1]);
    // cycle = 2*50000=100000, minus 150000 discount => floored at 0, not negative.

    $total = $this->metrics->mrrSnapshot()['total_mrr'];

    expect($total)->toBe(0);
});

it('excludes an inactive recurring template from the MRR snapshot', function () {
    $template = RecurringInvoice::factory()->create(['is_active' => false]);
    $template->items()->create(['description' => 'SEO', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'sort_order' => 1]);

    expect($this->metrics->mrrSnapshot()['total_mrr'])->toBe(0);
});

it('groups MRR by service sorted descending', function () {
    $seo = Service::factory()->create(['name' => 'SEO']);
    $gmb = Service::factory()->create(['name' => 'GMB']);
    $t1 = RecurringInvoice::factory()->create(['service_id' => $seo->id]);
    $t1->items()->create(['description' => 'x', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'sort_order' => 1]);
    $t2 = RecurringInvoice::factory()->create(['service_id' => $gmb->id]);
    $t2->items()->create(['description' => 'x', 'quantity' => 1, 'rate' => 300000, 'gst_rate' => 18, 'sort_order' => 1]);

    $byService = $this->metrics->mrrSnapshot()['by_service'];

    expect($byService[0]['name'])->toBe('GMB');
});

it('includes a contract expiring within 30 days and excludes one expiring later or never', function () {
    $expiring = RecurringInvoice::factory()->create(['end_date' => now()->addDays(15)]);
    $expiring->items()->create(['description' => 'x', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'sort_order' => 1]);
    $later = RecurringInvoice::factory()->create(['end_date' => now()->addDays(45)]);
    $later->items()->create(['description' => 'x', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'sort_order' => 1]);
    $never = RecurringInvoice::factory()->create(['end_date' => null]);
    $never->items()->create(['description' => 'x', 'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'sort_order' => 1]);

    expect($this->metrics->mrrSnapshot()['expiring_count'])->toBe(1);
});

// --- Client concentration -------------------------------------------------------

it('computes top 5 and top 10 percentages from a by_client breakdown', function () {
    $byClient = [
        ['name' => 'A', 'total' => 500000], ['name' => 'B', 'total' => 300000], ['name' => 'C', 'total' => 100000],
        ['name' => 'D', 'total' => 50000], ['name' => 'E', 'total' => 30000], ['name' => 'F', 'total' => 20000],
    ];
    $periodTotal = collect($byClient)->sum('total'); // 1000000

    $result = $this->metrics->clientConcentration($byClient, $periodTotal);

    expect($result['top5_pct'])->toBe(98.0)
        ->and($result['top10_pct'])->toBe(100.0);
});

it('guards against division by zero when the period total is zero', function () {
    $result = $this->metrics->clientConcentration([], 0);

    expect($result['top5_pct'])->toBe(0.0)->and($result['top10_pct'])->toBe(0.0);
});

it('passes the given by_client array through unchanged', function () {
    $byClient = [['name' => 'A', 'total' => 100]];

    expect($this->metrics->clientConcentration($byClient, 100)['clients'])->toBe($byClient);
});

// --- Pipeline funnel --------------------------------------------------------------

it('is company-wide, unlike the per-sales-rep dashboard pipeline', function () {
    $repA = User::factory()->role(UserRole::Sales)->create();
    $repB = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->create(['owner_id' => $repA->id, 'stage' => DealStage::Proposal]);
    Deal::factory()->create(['owner_id' => $repB->id, 'stage' => DealStage::Proposal]);
    Deal::factory()->create(['owner_id' => null, 'stage' => DealStage::Proposal]);

    $result = $this->metrics->pipelineFunnel(now()->startOfMonth(), now()->endOfMonth());

    expect(collect($result['pipeline'])->firstWhere('stage', 'Proposal')['deals'])->toBe(3);
});

it('backfills a stage with zero deals rather than omitting it', function () {
    $result = $this->metrics->pipelineFunnel(now()->startOfMonth(), now()->endOfMonth());

    expect($result['pipeline'])->toHaveCount(4)
        ->and(collect($result['pipeline'])->pluck('stage')->all())->toBe(['New', 'Contacted', 'Proposal', 'Negotiation']);
});

it('counts a deal won inside the period toward win rate', function () {
    Deal::factory()->create(['stage' => DealStage::Won, 'won_at' => now(), 'value' => 500000]);

    $result = $this->metrics->pipelineFunnel(now()->startOfMonth(), now()->endOfMonth());

    expect($result['won_count'])->toBe(1)->and($result['win_rate_pct'])->toBe(100);
});

it('counts a deal lost inside the period via updated_at, and excludes one lost outside the period', function () {
    $deal = Deal::factory()->create(['stage' => DealStage::New]);
    $deal->update(['stage' => DealStage::Lost]); // updated_at = now, inside this month

    $insidePeriod = $this->metrics->pipelineFunnel(now()->startOfMonth(), now()->endOfMonth());
    expect($insidePeriod['lost_count'])->toBe(1);

    $outsidePeriod = $this->metrics->pipelineFunnel(now()->subMonths(2)->startOfMonth(), now()->subMonths(2)->endOfMonth());
    expect($outsidePeriod['lost_count'])->toBe(0);
});

it('computes avg deal size and cycle length from won deals only, not blended with lost', function () {
    Deal::factory()->create(['stage' => DealStage::Won, 'won_at' => now(), 'created_at' => now()->subDays(10), 'value' => 1000000]);
    $lost = Deal::factory()->create(['stage' => DealStage::New, 'value' => 1]);
    $lost->update(['stage' => DealStage::Lost]);

    $result = $this->metrics->pipelineFunnel(now()->startOfMonth(), now()->endOfMonth());

    expect($result['avg_deal_size'])->toBe(1000000)
        ->and($result['avg_sales_cycle_days'])->toBe(10);
});

it('returns null for win rate, avg deal size and avg cycle when nothing closed in the period', function () {
    $result = $this->metrics->pipelineFunnel(now()->startOfMonth(), now()->endOfMonth());

    expect($result['win_rate_pct'])->toBeNull()
        ->and($result['avg_deal_size'])->toBeNull()
        ->and($result['avg_sales_cycle_days'])->toBeNull();
});

// --- HTTP / role gating ------------------------------------------------------------

it('lets admin see the full business overview page including financial detail', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now()->subDays(45), 'total' => 100000, 'amount_paid' => 0]);

    $this->actingAs($admin)->get(route('reports.business-overview'))
        ->assertOk()
        ->assertSee('Business Overview')
        ->assertSee('31–60 days overdue');
});

it('lets accounts see the same financial detail as admin', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('reports.business-overview'))
        ->assertOk()
        ->assertSee('31–60 days overdue');
});

it('shows manager the page but hides itemized financial detail', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.business-overview'))
        ->assertOk()
        ->assertSee('Business Overview')
        ->assertDontSee('31–60 days overdue');
});

it('forbids sales, support and intern from the business overview page', function () {
    foreach ([UserRole::Sales, UserRole::Support, UserRole::Intern] as $role) {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user)->get(route('reports.business-overview'))->assertForbidden();
    }
});

it('includes itemized overdue invoices in the admin CSV export but not the manager one', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_date' => now()->subDays(45), 'total' => 100000, 'amount_paid' => 0]);

    $adminCsv = $this->actingAs($admin)->get(route('reports.business-overview.export'));
    $adminCsv->assertOk();
    expect($adminCsv->headers->get('content-type'))->toContain('text/csv')
        ->and($adminCsv->streamedContent())->toContain('Overdue invoices');

    $managerCsv = $this->actingAs($manager)->get(route('reports.business-overview.export'));
    $managerCsv->assertOk();
    expect($managerCsv->streamedContent())->not->toContain('Overdue invoices');
});

it('changes the period-scoped funnel stats when a different financial year is selected', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Deal::factory()->create(['stage' => DealStage::Won, 'won_at' => now()->subYears(2), 'value' => 500000]);

    $currentFyStart = now()->month >= 4 ? now()->year : now()->year - 1;

    $current = $this->metrics->pipelineFunnel(
        Carbon::create($currentFyStart, 4, 1),
        Carbon::create($currentFyStart + 1, 3, 31)->endOfDay()
    );
    $priorFyStart = $currentFyStart - 2;
    $prior = $this->metrics->pipelineFunnel(
        Carbon::create($priorFyStart, 4, 1),
        Carbon::create($priorFyStart + 1, 3, 31)->endOfDay()
    );

    expect($current['won_count'])->toBe(0)->and($prior['won_count'])->toBe(1);
});
