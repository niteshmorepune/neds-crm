<?php

use App\Enums\PartnerCollectionMode;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use Illuminate\Support\Carbon;

it('creates one locked settlement per NedsCollects template billed within the target month', function () {
    $partner = Partner::factory()->create();
    $customer = Customer::factory()->create(['referring_partner_id' => $partner->id, 'referral_share_rate' => 20]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);
    Invoice::factory()->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $customer->id,
        'issue_date' => Carbon::create(2026, 6, 5), 'total' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-referral-settlements', ['--month' => '2026-06'])->assertSuccessful();

    $settlement = ReferralSettlement::where('recurring_invoice_id', $template->id)->whereDate('period_start', '2026-06-01')->first();

    expect($settlement)->not->toBeNull()
        ->and($settlement->billed_amount)->toBe(60_000 * 100)
        ->and($settlement->share_amount)->toBe(12_000 * 100)
        ->and($settlement->flow_mode)->toBe(PartnerCollectionMode::NedsCollects)
        ->and($settlement->finalized_at)->not->toBeNull();
});

it('never processes a PartnerCollects client, even if it somehow has a real invoice', function () {
    $partner = Partner::factory()->create();
    $customer = Customer::factory()->create([
        'referring_partner_id' => $partner->id,
        'referral_share_rate' => 20,
        'partner_collection_mode' => PartnerCollectionMode::PartnerCollects,
    ]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);
    Invoice::factory()->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $customer->id,
        'issue_date' => Carbon::create(2026, 6, 5), 'total' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-referral-settlements', ['--month' => '2026-06'])->assertSuccessful();

    expect(ReferralSettlement::where('recurring_invoice_id', $template->id)->exists())->toBeFalse();
});

it('skips a customer with no referral_share_rate set', function () {
    $partner = Partner::factory()->create();
    $customer = Customer::factory()->create(['referring_partner_id' => $partner->id, 'referral_share_rate' => null]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);
    Invoice::factory()->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $customer->id,
        'issue_date' => Carbon::create(2026, 6, 5), 'total' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-referral-settlements', ['--month' => '2026-06'])->assertSuccessful();

    expect(ReferralSettlement::where('recurring_invoice_id', $template->id)->exists())->toBeFalse();
});

it('re-running for the same month updates the existing settlement instead of duplicating', function () {
    $partner = Partner::factory()->create();
    $customer = Customer::factory()->create(['referring_partner_id' => $partner->id, 'referral_share_rate' => 20]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);
    Invoice::factory()->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $customer->id,
        'issue_date' => Carbon::create(2026, 6, 5), 'total' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-referral-settlements', ['--month' => '2026-06'])->assertSuccessful();

    Invoice::factory()->create([
        'recurring_invoice_id' => $template->id, 'customer_id' => $customer->id,
        'issue_date' => Carbon::create(2026, 6, 20), 'total' => 40_000 * 100,
    ]);

    $this->artisan('app:finalize-referral-settlements', ['--month' => '2026-06'])->assertSuccessful();

    expect(ReferralSettlement::where('recurring_invoice_id', $template->id)->whereDate('period_start', '2026-06-01')->count())->toBe(1);

    $settlement = ReferralSettlement::where('recurring_invoice_id', $template->id)->whereDate('period_start', '2026-06-01')->first();
    expect($settlement->billed_amount)->toBe(100_000 * 100);
});
