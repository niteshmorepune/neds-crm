<?php

use App\Enums\PartnerCollectionMode;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use App\Models\Service;

beforeEach(function () {
    $this->partnerA = Partner::factory()->portalUser()->create(['name' => 'Alpha Partner']);
    $this->partnerB = Partner::factory()->portalUser()->create(['name' => 'Bravo Partner']);
});

it('shows the monthly collections grid with real amounts for a NedsCollects client', function () {
    $client = Customer::factory()->create(['company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id]);
    $seo = Service::factory()->create(['name' => 'SEO']);
    $template = RecurringInvoice::factory()->create(['customer_id' => $client->id, 'service_id' => $seo->id, 'start_date' => now()->subMonths(2)]);
    Invoice::factory()->create(['recurring_invoice_id' => $template->id, 'customer_id' => $client->id, 'issue_date' => now()->startOfMonth(), 'total' => 300000]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $client))
        ->assertOk()
        ->assertSee('Monthly Collections')
        ->assertSee('SEO')
        ->assertSee('₹3,000.00');
});

it('shows a partner-owes-NEDS card for an unsettled PartnerCollects settlement, and hides it once settled', function () {
    $client = Customer::factory()->create([
        'company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects, 'referral_share_rate' => 20,
    ]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $client->id]);
    $settlement = ReferralSettlement::factory()->partnerCollects()->create([
        'customer_id' => $client->id, 'partner_id' => $this->partnerA->id, 'recurring_invoice_id' => $template->id,
        'period_start' => now()->startOfMonth(), 'billed_amount' => 300000, 'share_rate' => 20, 'share_amount' => 60000,
    ]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $client))
        ->assertOk()
        ->assertSee('Your share owed to NEDS')
        ->assertSee('₹600.00');

    $settlement->update(['settled_at' => now()]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $client))
        ->assertOk()
        ->assertDontSee('Your share owed to NEDS')
        ->assertSee('already settled');
});

it('shows an owed-to-you-by-NEDS card for an unsettled NedsCollects settlement', function () {
    $client = Customer::factory()->create(['company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id, 'referral_share_rate' => 20]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $client->id]);
    ReferralSettlement::factory()->create([
        'customer_id' => $client->id, 'partner_id' => $this->partnerA->id, 'recurring_invoice_id' => $template->id,
        'period_start' => now()->startOfMonth(), 'billed_amount' => 300000, 'share_rate' => 20, 'share_amount' => 60000,
    ]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $client))
        ->assertOk()
        ->assertSee('Owed to you by NEDS')
        ->assertSee('₹600.00');
});

it('never shows another partner\'s settlement data', function () {
    $mine = Customer::factory()->create(['referring_partner_id' => $this->partnerA->id]);
    $theirs = Customer::factory()->create(['referring_partner_id' => $this->partnerB->id, 'referral_share_rate' => 20]);
    $theirTemplate = RecurringInvoice::factory()->create(['customer_id' => $theirs->id]);
    ReferralSettlement::factory()->create([
        'customer_id' => $theirs->id, 'partner_id' => $this->partnerB->id, 'recurring_invoice_id' => $theirTemplate->id,
        'share_amount' => 999999,
    ]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertDontSee('9,999.99');
});

it('shows the portfolio net-position tile on the dashboard when something is unsettled', function () {
    $client = Customer::factory()->create(['referring_partner_id' => $this->partnerA->id, 'referral_share_rate' => 20]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $client->id]);
    ReferralSettlement::factory()->create([
        'customer_id' => $client->id, 'partner_id' => $this->partnerA->id, 'recurring_invoice_id' => $template->id,
        'share_amount' => 60000,
    ]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee('Owed to you by NEDS')
        ->assertSee('₹600.00');
});

it('hides the portfolio net-position tile when there is nothing unsettled', function () {
    Customer::factory()->create(['referring_partner_id' => $this->partnerA->id]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertDontSee('Owed to you by NEDS');
});
