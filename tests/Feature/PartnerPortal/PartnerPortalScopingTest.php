<?php

use App\Enums\ContentStatus;
use App\Models\ContentPiece;
use App\Models\Customer;
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
