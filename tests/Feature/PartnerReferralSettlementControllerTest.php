<?php

use App\Enums\PartnerCollectionMode;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->partner = Partner::factory()->create();
    $this->customer = Customer::factory()->create([
        'referring_partner_id' => $this->partner->id,
        'referral_share_rate' => 20,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects,
    ]);
    $this->template = RecurringInvoice::factory()->create(['customer_id' => $this->customer->id]);
});

it('admin can record a PartnerCollects client\'s monthly collection', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.referral-settlements.store', $this->partner), [
            'recurring_invoice_id' => $this->template->id,
            'period_start' => '2026-08',
            'billed_amount' => '3000',
        ])
        ->assertRedirect();

    $settlement = ReferralSettlement::where('recurring_invoice_id', $this->template->id)->first();
    expect($settlement)->not->toBeNull()
        ->and($settlement->billed_amount)->toBe(300000)
        ->and($settlement->share_amount)->toBe(60000);
});

it('sales cannot record a monthly collection', function () {
    actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->post(route('partners.referral-settlements.store', $this->partner), [
            'recurring_invoice_id' => $this->template->id,
            'period_start' => '2026-08',
            'billed_amount' => '3000',
        ])
        ->assertForbidden();
});

it('rejects recording against a NedsCollects client\'s template', function () {
    $nedsCustomer = Customer::factory()->create(['referring_partner_id' => $this->partner->id, 'referral_share_rate' => 20]);
    $nedsTemplate = RecurringInvoice::factory()->create(['customer_id' => $nedsCustomer->id]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.referral-settlements.store', $this->partner), [
            'recurring_invoice_id' => $nedsTemplate->id,
            'period_start' => '2026-08',
            'billed_amount' => '3000',
        ])
        ->assertStatus(422);

    expect(ReferralSettlement::where('recurring_invoice_id', $nedsTemplate->id)->exists())->toBeFalse();
});

it('404s recording against a template belonging to a different partner\'s client', function () {
    $otherPartner = Partner::factory()->create();
    $otherCustomer = Customer::factory()->create(['referring_partner_id' => $otherPartner->id, 'partner_collection_mode' => PartnerCollectionMode::PartnerCollects]);
    $otherTemplate = RecurringInvoice::factory()->create(['customer_id' => $otherCustomer->id]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.referral-settlements.store', $this->partner), [
            'recurring_invoice_id' => $otherTemplate->id,
            'period_start' => '2026-08',
            'billed_amount' => '3000',
        ])
        ->assertNotFound();
});

it('admin can settle a settlement row', function () {
    $settlement = ReferralSettlement::factory()->create(['partner_id' => $this->partner->id, 'customer_id' => $this->customer->id, 'recurring_invoice_id' => $this->template->id]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($admin)
        ->post(route('partners.referral-settlements.settle', [$this->partner, $settlement]))
        ->assertRedirect();

    expect($settlement->fresh()->isSettled())->toBeTrue()
        ->and($settlement->fresh()->settled_by)->toBe($admin->id);
});

it('404s settling a settlement row belonging to a different partner', function () {
    $otherPartner = Partner::factory()->create();
    $settlement = ReferralSettlement::factory()->create(['partner_id' => $otherPartner->id]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.referral-settlements.settle', [$this->partner, $settlement]))
        ->assertNotFound();
});
