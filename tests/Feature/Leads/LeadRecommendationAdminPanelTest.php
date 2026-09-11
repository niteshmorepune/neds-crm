<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\OfferPurchase;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows the recommendation and offer panel on the lead page once goal and budget are both set', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::GrowBusiness, 'budget_range' => LeadBudgetRange::ThreeToSix]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Recommendation & Offer')
        ->assertSee('Website Growth Audit')
        ->assertSee('₹499')
        ->assertSee('Not viewed yet')
        ->assertSee('No purchase yet');
});

it('does not show the recommendation panel when goal or budget is missing', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::GrowBusiness, 'budget_range' => null]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Recommendation & Offer');
});

it('shows recommendation-viewed/offer-viewed timestamps and payment status once real activity has happened', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_viewed_at' => now()->subHour(),
        'offer_viewed_at' => now()->subMinutes(30),
    ]);
    OfferPurchase::create([
        'offer_key' => OfferKey::LeadGenerationAudit->value,
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Paid,
        'razorpay_order_id' => 'order_admin1',
        'razorpay_payment_id' => 'pay_admin1',
        'lead_id' => $lead->id,
        'paid_at' => now(),
    ]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Not viewed yet')
        ->assertSee('Successful');
});
