<?php

use App\Enums\ContentStatus;
use App\Enums\InvoiceStatus;
use App\Models\ContentPiece;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Quotation;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->partnerA = Partner::factory()->portalUser()->create(['name' => 'Alpha Partner']);
    $this->partnerB = Partner::factory()->portalUser()->create(['name' => 'Bravo Partner']);
});

it('shows the partner only their own referred clients and content pieces', function () {
    $mine = Customer::factory()->create(['company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id]);
    $theirs = Customer::factory()->create(['company_name' => 'Bravo Client', 'referring_partner_id' => $this->partnerB->id]);

    $myPiece = ContentPiece::factory()->create(['partner_id' => $this->partnerA->id, 'title' => 'Alpha Reel']);
    $theirPiece = ContentPiece::factory()->create(['partner_id' => $this->partnerB->id, 'title' => 'Bravo Reel']);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee('Alpha Client')->assertDontSee('Bravo Client')
        ->assertSee('Alpha Reel')->assertDontSee('Bravo Reel');
});

it('shows the partner only quotations for their own referred clients, with a working PDF download', function () {
    $mine = Customer::factory()->create(['company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id]);
    $theirs = Customer::factory()->create(['company_name' => 'Bravo Client', 'referring_partner_id' => $this->partnerB->id]);

    $myQuotation = Quotation::factory()->create(['customer_id' => $mine->id, 'number' => 'QTN/2026-27/8001']);
    $theirQuotation = Quotation::factory()->create(['customer_id' => $theirs->id, 'number' => 'QTN/2026-27/8002']);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee('QTN/2026-27/8001')
        ->assertDontSee('QTN/2026-27/8002');

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.quotations.pdf', $myQuotation))
        ->assertOk();

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.quotations.pdf', $theirQuotation))
        ->assertForbidden();
});

it('lets a partner upload files against their own content piece and advances a waiting status', function () {
    $piece = ContentPiece::factory()->agencyLed()->create(['partner_id' => $this->partnerA->id]);
    $file = UploadedFile::fake()->create('deliverable.pdf', 100, 'application/pdf');

    $this->actingAs($this->partnerA, 'partner')
        ->post(route('partner-portal.content-pieces.upload', $piece), ['files' => [$file]])
        ->assertRedirect();

    expect($piece->fresh()->status)->toBe(ContentStatus::Received);
    expect($piece->fresh()->attachments()->count())->toBe(1);
});

it('404s when a partner tries to upload against another partner\'s content piece', function () {
    $theirPiece = ContentPiece::factory()->create(['partner_id' => $this->partnerB->id]);
    $file = UploadedFile::fake()->create('deliverable.pdf', 100, 'application/pdf');

    $this->actingAs($this->partnerA, 'partner')
        ->post(route('partner-portal.content-pieces.upload', $theirPiece), ['files' => [$file]])
        ->assertNotFound();
});

it('does not let an internal user session into the partner portal', function () {
    $this->get(route('partner-portal.home'))->assertRedirect(route('partner-portal.login'));
});

it('shows each referred client\'s own outstanding amount and overdue count on the dashboard', function () {
    $client = Customer::factory()->create(['company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id]);
    Invoice::factory()->create([
        'customer_id' => $client->id, 'status' => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(3), 'total' => 75000, 'amount_paid' => 0,
    ]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee('₹750.00')
        ->assertSee('1 overdue');
});

it('lets a partner drill into their own referred client\'s account page, with invoices/quotations/projects', function () {
    $client = Customer::factory()->create(['company_name' => 'Alpha Client', 'referring_partner_id' => $this->partnerA->id]);
    Invoice::factory()->create([
        'customer_id' => $client->id, 'status' => InvoiceStatus::Overdue,
        'invoice_number' => 'NEDS/2026-27/7001', 'due_date' => now()->subDays(3),
        'total' => 75000, 'amount_paid' => 0,
    ]);
    Quotation::factory()->create(['customer_id' => $client->id, 'number' => 'QTN/2026-27/7001']);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $client))
        ->assertOk()
        ->assertSee('Alpha Client')
        ->assertSee('NEDS/2026-27/7001')
        ->assertSee('QTN/2026-27/7001');
});

it('404s when a partner tries to view another partner\'s referred client', function () {
    $theirClient = Customer::factory()->create(['referring_partner_id' => $this->partnerB->id]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $theirClient))
        ->assertNotFound();
});

it('404s when a partner tries to view a client not referred by anyone', function () {
    $unowned = Customer::factory()->create(['referring_partner_id' => null]);

    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.clients.show', $unowned))
        ->assertNotFound();
});

it('shows a reseller partner their consolidated "Your Account" invoices, and each referred client as billed via that account', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);
    $reseller = Partner::factory()->portalUser()->create(['name' => 'Brand-Whiz Partner', 'billing_customer_id' => $billTo->id]);
    $subClient = Customer::factory()->create(['company_name' => 'Sub Client Co', 'referring_partner_id' => $reseller->id]);

    Invoice::factory()->create([
        'customer_id' => $billTo->id, 'status' => InvoiceStatus::Overdue,
        'invoice_number' => 'NEDS/2026-27/7101', 'due_date' => now()->subDays(2),
        'total' => 120000, 'amount_paid' => 0,
    ]);
    Quotation::factory()->create(['customer_id' => $billTo->id, 'number' => 'QTN/2026-27/7101']);

    $response = $this->actingAs($reseller, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee('Your Account')
        ->assertSee('NEDS/2026-27/7101')
        ->assertSee('QTN/2026-27/7101')
        ->assertSee('Sub Client Co')
        ->assertSee('Billed via your account');

    $response->assertSee(route('partner-portal.quotations.pdf', Quotation::where('number', 'QTN/2026-27/7101')->first()));
});

it('lets a reseller partner download a PDF for a quotation billed to their billing customer', function () {
    $billTo = Customer::factory()->create();
    $reseller = Partner::factory()->portalUser()->create(['billing_customer_id' => $billTo->id]);
    $quotation = Quotation::factory()->create(['customer_id' => $billTo->id]);

    $this->actingAs($reseller, 'partner')
        ->get(route('partner-portal.quotations.pdf', $quotation))
        ->assertOk();
});

it('shows a friendlier empty state for content submissions instead of a bare "no submissions"', function () {
    $this->actingAs($this->partnerA, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee("we'll open a submission here", false);
});
