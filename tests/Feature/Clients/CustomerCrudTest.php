<?php

use App\Enums\CustomerStatus;
use App\Enums\PartnerCollectionMode;
use App\Enums\ReferralShareType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Partner;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('creates a client with valid data and derives the state name', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Acme Digital Pvt Ltd',
            'gstin' => '27ABCDE1234F1Z5',
            'email' => 'hello@acme.test',
            'state_code' => '27',
            'country' => 'India',
            'tags' => 'seo, retainer',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Acme Digital Pvt Ltd');

    expect($customer)->not->toBeNull()
        ->and($customer->state)->toBe('Maharashtra')
        ->and($customer->state_code)->toBe('27')
        ->and($customer->tags)->toBe(['seo', 'retainer']);
});

it('saves a referred client\'s collection mode and referral share rate', function () {
    $partner = Partner::factory()->create();

    $this->actingAs($this->admin)->post(route('clients.store'), [
        'company_name' => 'Referred Client Co',
        'country' => 'India',
        'status' => CustomerStatus::Active->value,
        'referring_partner_id' => $partner->id,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects->value,
        'referral_share_rate' => '25.5',
    ])->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Referred Client Co');

    expect($customer->partner_collection_mode)->toBe(PartnerCollectionMode::PartnerCollects)
        ->and((float) $customer->referral_share_rate)->toBe(25.5)
        ->and($customer->isPartnerCollected())->toBeTrue();
});

it('saves a referred client\'s fixed-amount referral share, converting rupees to paise', function () {
    $partner = Partner::factory()->create();

    $this->actingAs($this->admin)->post(route('clients.store'), [
        'company_name' => 'Fixed Share Co',
        'country' => 'India',
        'status' => CustomerStatus::Active->value,
        'referring_partner_id' => $partner->id,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects->value,
        'referral_share_type' => ReferralShareType::FixedAmount->value,
        'referral_share_fixed_amount' => '10000',
    ])->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Fixed Share Co');

    expect($customer->referral_share_type)->toBe(ReferralShareType::FixedAmount)
        ->and($customer->referral_share_fixed_amount)->toBe(1000000);
});

it('defaults a referred client to NedsCollects when no collection mode is chosen', function () {
    $partner = Partner::factory()->create();

    $this->actingAs($this->admin)->post(route('clients.store'), [
        'company_name' => 'Default Mode Co',
        'country' => 'India',
        'status' => CustomerStatus::Active->value,
        'referring_partner_id' => $partner->id,
    ])->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Default Mode Co');

    expect($customer->partner_collection_mode)->toBeNull()
        ->and($customer->isPartnerCollected())->toBeFalse();
});

it('saves a referred client billed via a third party and resolves billingTarget() to it', function () {
    $partner = Partner::factory()->create();
    $thirdParty = Customer::factory()->create(['company_name' => 'Pulse Orbit Entertainment Pvt Ltd']);

    $this->actingAs($this->admin)->post(route('clients.store'), [
        'company_name' => 'Terragenix Solutions',
        'country' => 'India',
        'status' => CustomerStatus::Active->value,
        'referring_partner_id' => $partner->id,
        'partner_collection_mode' => PartnerCollectionMode::BilledViaThirdParty->value,
        'billed_via_customer_id' => $thirdParty->id,
    ])->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Terragenix Solutions');

    expect($customer->partner_collection_mode)->toBe(PartnerCollectionMode::BilledViaThirdParty)
        ->and($customer->billed_via_customer_id)->toBe($thirdParty->id)
        ->and($customer->isPartnerCollected())->toBeFalse()
        ->and($customer->billingTarget()->is($thirdParty))->toBeTrue();
});

it('requires a billed-via company when the collection mode is BilledViaThirdParty', function () {
    $partner = Partner::factory()->create();

    $this->actingAs($this->admin)->post(route('clients.store'), [
        'company_name' => 'Missing Third Party Co',
        'country' => 'India',
        'status' => CustomerStatus::Active->value,
        'referring_partner_id' => $partner->id,
        'partner_collection_mode' => PartnerCollectionMode::BilledViaThirdParty->value,
    ])->assertSessionHasErrors('billed_via_customer_id');

    expect(Customer::where('company_name', 'Missing Third Party Co')->exists())->toBeFalse();
});

it('rejects a client being billed via itself', function () {
    $partner = Partner::factory()->create();
    $customer = Customer::factory()->create(['referring_partner_id' => $partner->id]);

    $this->actingAs($this->admin)
        ->put(route('clients.update', $customer), [
            'company_name' => $customer->company_name,
            'country' => 'India',
            'status' => CustomerStatus::Active->value,
            'referring_partner_id' => $partner->id,
            'partner_collection_mode' => PartnerCollectionMode::BilledViaThirdParty->value,
            'billed_via_customer_id' => $customer->id,
        ])
        ->assertSessionHasErrors('billed_via_customer_id');
});

it('saves and displays an alternate phone number', function () {
    $this->actingAs($this->admin)->post(route('clients.store'), [
        'company_name' => 'Acme Digital Pvt Ltd',
        'phone' => '9876543210',
        'alternate_phone' => '9123456780',
        'country' => 'India',
        'status' => CustomerStatus::Active->value,
    ])->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Acme Digital Pvt Ltd');
    expect($customer->alternate_phone)->toBe('9123456780');

    $this->actingAs($this->admin)->get(route('clients.show', $customer))
        ->assertOk()
        ->assertSee('9123456780');
});

it('does not show an Alternate phone row on a client when none is set', function () {
    $customer = Customer::factory()->create(['alternate_phone' => null]);

    $this->actingAs($this->admin)->get(route('clients.show', $customer))
        ->assertOk()
        ->assertDontSee('Alternate phone');
});

it('records the referring partner on a client that was directly imported (no lead/deal history)', function () {
    $partner = Partner::factory()->create(['name' => 'Referral Agency Co']);
    $client = Customer::factory()->create(['referring_partner_id' => null]);

    $this->actingAs($this->admin)
        ->put(route('clients.update', $client), [
            'company_name' => $client->company_name,
            'country' => 'India',
            'status' => CustomerStatus::Active->value,
            'referring_partner_id' => $partner->id,
        ])
        ->assertRedirect();

    expect($client->fresh()->referring_partner_id)->toBe($partner->id)
        ->and($client->fresh()->referringPartner->name)->toBe('Referral Agency Co')
        ->and($partner->referredCustomers()->pluck('id'))->toContain($client->id);
});

it('filters the clients list by referring partner', function () {
    $partnerA = Partner::factory()->create();
    $partnerB = Partner::factory()->create();
    $viaA = Customer::factory()->create(['referring_partner_id' => $partnerA->id]);
    $viaB = Customer::factory()->create(['referring_partner_id' => $partnerB->id]);

    $response = $this->actingAs($this->admin)
        ->get(route('clients.index', ['referring_partner_id' => $partnerA->id, 'status' => 'all']));

    $response->assertOk()->assertSee($viaA->company_name)->assertDontSee($viaB->company_name);
});

it('rejects an invalid GSTIN', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Bad GST Co',
            'gstin' => 'INVALID12345678',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionHasErrors('gstin');

    expect(Customer::where('company_name', 'Bad GST Co')->exists())->toBeFalse();
});

it('rejects a duplicate GSTIN', function () {
    Customer::factory()->create(['gstin' => '27ABCDE1234F1Z5']);

    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Dup Co',
            'gstin' => '27ABCDE1234F1Z5',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionHasErrors('gstin');
});

it('allows reusing a GSTIN that only a soft-deleted client holds', function () {
    Customer::factory()->create(['gstin' => '27DUPXX9999X1Z1'])->delete();

    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Re-onboarded Co',
            'gstin' => '27DUPXX9999X1Z1',
            'country' => 'India',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect();

    expect(Customer::where('gstin', '27DUPXX9999X1Z1')->count())->toBe(1);
});

it('allows editing a client to a GSTIN that only a soft-deleted client holds', function () {
    Customer::factory()->create(['gstin' => '27DUPYY8888Y1Z2'])->delete();
    $customer = Customer::factory()->create(['company_name' => 'Existing Co']);

    $this->actingAs($this->admin)
        ->put(route('clients.update', $customer), [
            'company_name' => 'Existing Co',
            'gstin' => '27DUPYY8888Y1Z2',
            'country' => 'India',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionDoesntHaveErrors('gstin');

    expect($customer->fresh()->gstin)->toBe('27DUPYY8888Y1Z2');
});

it('requires a company name', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), ['status' => CustomerStatus::Active->value])
        ->assertSessionHasErrors('company_name');
});

it('creates a client flagged as GST-exempt', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Non-GST Co',
            'country' => 'India',
            'status' => CustomerStatus::Active->value,
            'gst_exempt' => '1',
        ])
        ->assertRedirect();

    expect(Customer::firstWhere('company_name', 'Non-GST Co')->gst_exempt)->toBeTrue();
});

it('unchecking the GST-exempt box on update actually clears it (not just leaves it untouched)', function () {
    $customer = Customer::factory()->create(['gst_exempt' => true]);

    $this->actingAs($this->admin)
        ->put(route('clients.update', $customer), [
            'company_name' => $customer->company_name,
            'country' => 'India',
            'status' => CustomerStatus::Active->value,
            // gst_exempt intentionally omitted, exactly like an unchecked checkbox submits
        ])
        ->assertRedirect();

    expect($customer->fresh()->gst_exempt)->toBeFalse();
});

it('updates a client', function () {
    $customer = Customer::factory()->create(['company_name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('clients.update', $customer), [
            'company_name' => 'New Name',
            'status' => CustomerStatus::Inactive->value,
            'country' => 'India',
        ])
        ->assertRedirect(route('clients.show', $customer));

    expect($customer->fresh())
        ->company_name->toBe('New Name')
        ->status->toBe(CustomerStatus::Inactive);
});

it('soft deletes a client', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('clients.destroy', $customer))
        ->assertRedirect(route('clients.index'));

    $this->assertSoftDeleted($customer);
});

it('cascade-deletes related records when a client is deleted', function () {
    $customer = Customer::factory()->create();
    $deal = Deal::factory()->create(['customer_id' => $customer->id]);
    $ticket = Ticket::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($this->admin)
        ->delete(route('clients.destroy', $customer))
        ->assertRedirect(route('clients.index'));

    $this->assertSoftDeleted($customer);
    $this->assertSoftDeleted($deal);
    $this->assertSoftDeleted($ticket);
});
