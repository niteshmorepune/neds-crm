<?php

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
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

it('shows the deal stage label, prefixed "Deal: " so it never reads as the lead\'s own status, for a non-Won deal', function (DealStage $stage) {
    // Real confusion (2026-09-02): a bare "Lost" caption under a green
    // "Converted" badge looked like a contradiction. The lead genuinely
    // stays Converted forever (a real Deal was created — a historical
    // fact) even if that Deal is later Lost; the prefix disambiguates
    // which of the two the caption is describing.
    $deal = Deal::factory()->stage($stage)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: '.$stage->label());
})->with([
    'proposal' => DealStage::Proposal,
    'negotiation' => DealStage::Negotiation,
    'contacted' => DealStage::Contacted,
    'lost' => DealStage::Lost,
]);

it('shows "Deal: Won (unbilled)" for a Won deal with no invoices at all', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: Won (unbilled)');
});

it('shows "Deal: Won (unpaid)" for a Won deal whose invoice has zero paid', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 100000, 'amount_paid' => 0]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: Won (unpaid)');
});

it('shows "Deal: Won (partial payment)" for a Won deal partially paid', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 100000, 'amount_paid' => 40000]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: Won (partial payment)');
});

it('shows plain "Deal: Won" for a Won deal fully paid, even across multiple invoices', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 60000, 'amount_paid' => 60000]);
    Invoice::factory()->create(['deal_id' => $deal->id, 'customer_id' => $deal->customer_id, 'total' => 40000, 'amount_paid' => 40000]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: Won');
});

it('counts an invoice with no deal_id at all toward the same customer\'s Won deal', function () {
    // Real production case (Devraj Kanakappan/ADTA Group): a fully-paid
    // invoice logged via the manual "Log Invoice" flow with deal_id=NULL
    // was silently invisible to the first version of this method.
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    Invoice::factory()->create(['deal_id' => null, 'customer_id' => $deal->customer_id, 'total' => 157500, 'amount_paid' => 157500]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: Won');
});

it('never counts an invoice explicitly tied to a DIFFERENT deal for the same customer', function () {
    $customer = Customer::factory()->create();
    $deal = Deal::factory()->stage(DealStage::Won)->create(['customer_id' => $customer->id]);
    $otherDeal = Deal::factory()->create(['customer_id' => $customer->id]);
    // Fully paid, but attributed to the OTHER deal — must not count here.
    Invoice::factory()->create(['deal_id' => $otherDeal->id, 'customer_id' => $customer->id, 'total' => 100000, 'amount_paid' => 100000]);
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal: Won (unbilled)');
});

it('shows "Deal removed" when the linked deal has been soft-deleted', function () {
    // Real production case (Aakash Birari, Rajneer Envitech): the Lead
    // still points at a converted_deal_id whose Deal row was later
    // soft-deleted — convertedDeal() excludes trashed rows by default, so
    // this must be checked explicitly rather than silently returning null.
    $deal = Deal::factory()->stage(DealStage::Won)->create();
    $dealId = $deal->id;
    $deal->delete();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $dealId]);

    expect($lead->conversionOutcomeLabel())->toBe('Deal removed');
});

it('shows the conversion outcome caption on the Lead Generation list', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $deal->id, 'name' => 'Test Outcome Lead']);

    $this->actingAs($this->admin)
        ->get(route('leads.index', ['status' => LeadStatus::Converted->value]))
        ->assertOk()
        ->assertSee('Test Outcome Lead')
        ->assertSee('Deal: Negotiation');
});

it('colors "Deal: Lost" red and "Deal: Won..." green, distinct from the plain gray pipeline captions', function () {
    $lostDeal = Deal::factory()->stage(DealStage::Lost)->create();
    $lostLead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $lostDeal->id]);

    $wonDeal = Deal::factory()->stage(DealStage::Won)->create();
    $wonLead = Lead::factory()->create(['status' => LeadStatus::Converted, 'converted_deal_id' => $wonDeal->id]);

    $html = $this->actingAs($this->admin)
        ->get(route('leads.index', ['status' => LeadStatus::Converted->value]))
        ->getContent();

    expect($html)->toContain('text-red-500">Deal: Lost')
        ->and($html)->toContain('text-green-600">Deal: Won (unbilled)');
});
