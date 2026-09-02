<?php

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('returns null for a lead that is not Converted', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Qualified]);

    expect($lead->conversionOutcomeLabel())->toBeNull();
});

it('returns null for a Converted lead with no linked deal', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => null]);

    expect($lead->conversionOutcomeLabel())->toBeNull();
});

it('shows the deal stage label for a non-Won deal', function (DealStage $stage) {
    $deal = Deal::factory()->stage($stage)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe($stage->label());
})->with([
    'proposal' => DealStage::Proposal,
    'negotiation' => DealStage::Negotiation,
    'contacted' => DealStage::Contacted,
    'lost' => DealStage::Lost,
]);

it('shows "Won (unbilled)" for a Won deal with no invoices at all', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Won (unbilled)');
});

it('shows "Won (unpaid)" for a Won deal whose invoice has zero paid', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 100000, 'amount_paid' => 0]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Won (unpaid)');
});

it('shows "Won (partial payment)" for a Won deal partially paid', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 100000, 'amount_paid' => 40000]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Won (partial payment)');
});

it('shows plain "Won" for a Won deal fully paid, even across multiple invoices', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 60000, 'amount_paid' => 60000]);
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 40000, 'amount_paid' => 40000]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Won');
});

it('shows the conversion outcome caption on the Lead Generation list', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id, 'name' => 'Test Outcome Lead']);

    $this->actingAs($this->admin)
        ->get(route('leads.index', ['status' => LeadStatus::Converted->value]))
        ->assertOk()
        ->assertSee('Test Outcome Lead')
        ->assertSee('Negotiation');
});
