<?php

use App\Enums\CrmQueryType;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\User;
use App\Services\CrmQueryCatalog;

beforeEach(function () {
    $this->catalog = app(CrmQueryCatalog::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

function figureValue(array $figures, string $label): ?string
{
    return collect($figures)->firstWhere('label', $label)['value'] ?? null;
}

it('reports sales pipeline KPIs from real won deals', function () {
    Deal::factory()->stage(DealStage::Won)->create(['value' => 500000, 'won_at' => now()]);

    $figures = $this->catalog->run(CrmQueryType::SalesPipelineKpis, $this->admin);

    expect(figureValue($figures, 'Won this month'))->toBe('₹5,000.00');
});

it('reports flagged clients from Client Radar', function () {
    $customer = Customer::factory()->create(['company_name' => 'Flagged Co', 'status' => 'active']);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    $figures = $this->catalog->run(CrmQueryType::ClientRadar, $this->admin);

    expect(figureValue($figures, 'Clients flagged'))->toBe('1')
        ->and(collect($figures)->firstWhere('label', 'Flagged Co')['value'])->toContain('Overdue Invoice');
});

it('reports revenue summary for the current financial year', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'issue_date' => now(), 'total' => 118000]);

    $figures = $this->catalog->run(CrmQueryType::RevenueSummary, $this->admin);

    expect(figureValue($figures, 'Total revenue (this FY)'))->toBe('₹1,180.00');
});

it('reports service breakdown per service line', function () {
    $service = Service::factory()->create(['name' => 'SEO']);
    Deal::factory()->stage(DealStage::Won)->create(['service_id' => $service->id, 'value' => 200000, 'won_at' => now()]);

    $figures = $this->catalog->run(CrmQueryType::ServiceBreakdown, $this->admin);

    expect(figureValue($figures, 'SEO'))->toContain('₹2,000.00');
});

it('reports lead source performance for the current month', function () {
    Lead::factory()->create(['source' => LeadSource::Website, 'created_at' => now()]);

    $figures = $this->catalog->run(CrmQueryType::LeadSourcePerformance, $this->admin);

    expect(figureValue($figures, 'Total leads this month'))->toBe('1');
});

it('reports a cash forecast total', function () {
    RecurringInvoice::factory()->create(['is_active' => true]);

    $figures = $this->catalog->run(CrmQueryType::CashForecast, $this->admin);

    expect(figureValue($figures, 'Total forecast (next 3 months)'))->not->toBeNull();
});

it('reports an MRR snapshot total', function () {
    $template = RecurringInvoice::factory()->create(['is_active' => true]);
    $template->items()->create(['description' => 'Retainer', 'sac_code' => '998361', 'quantity' => 1, 'rate' => 500000, 'gst_rate' => 18]);

    $figures = $this->catalog->run(CrmQueryType::MrrSnapshot, $this->admin);

    expect(figureValue($figures, 'Total MRR'))->toBe('₹5,000.00');
});

it('reports AR aging total outstanding', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Overdue, 'total' => 300000, 'amount_paid' => 0]);

    $figures = $this->catalog->run(CrmQueryType::ArAging, $this->admin);

    expect(figureValue($figures, 'Total outstanding'))->toBe('₹3,000.00');
});

it('reports the rep leaderboard sorted by won-this-month value', function () {
    $rep = User::factory()->role(UserRole::Sales)->create(['name' => 'Top Rep']);
    Deal::factory()->stage(DealStage::Won)->create(['owner_id' => $rep->id, 'value' => 100000, 'won_at' => now()]);

    $figures = $this->catalog->run(CrmQueryType::RepLeaderboard, $this->admin);

    expect(figureValue($figures, 'Top Rep'))->toContain('₹1,000.00');
});

it('reports needs-attention counts', function () {
    // Deal::booted()'s saving hook stamps stage_changed_at=now() whenever
    // `stage` is dirty (true on initial create) — set the backdated value
    // in a second, stage-untouched update so the hook doesn't overwrite it.
    $deal = Deal::factory()->stage(DealStage::New)->create();
    $deal->update(['stage_changed_at' => now()->subDays(20)]);

    $figures = $this->catalog->run(CrmQueryType::NeedsAttention, $this->admin);

    expect(figureValue($figures, 'Stale in current stage'))->toBe('1');
});

it('reports AI usage summary for the current month', function () {
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'created_at' => now()]);

    $figures = $this->catalog->run(CrmQueryType::AiUsageSummary, $this->admin);

    expect(figureValue($figures, 'AI calls this month'))->toBe('1');
});

it('gives every query type a resolvable report route', function (CrmQueryType $type) {
    $route = $type->reportRoute();

    expect(route($route['name']))->toBeString()
        ->and($route['label'])->not->toBeEmpty();
})->with(fn () => CrmQueryType::cases());
