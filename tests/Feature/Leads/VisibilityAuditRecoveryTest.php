<?php

use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Services\VisibilityAuditFunnelMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('shows the funnel stage summary tiles with real counts', function () {
    Queue::fake(); // LeadObserver dispatches side-effect jobs on create — see VisibilityAuditFirstInviteTest for why this is faked here too.
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    Lead::factory()->create(['source' => LeadSource::MetaAds, 'service_id' => $gmb->id, 'visibility_audit_invited_at' => now()]);
    Lead::factory()->create(['source' => LeadSource::MetaAds, 'service_id' => $gmb->id]);

    $this->actingAs($this->admin)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('Eligible leads')
        ->assertSeeInOrder(['Eligible leads', '2'])
        ->assertSeeInOrder(['Invited via WhatsApp', '1']);
});

it('renders the recovery queue page', function () {
    $this->actingAs($this->admin)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('Visibility Audit Funnel');
});

it('redirects a logged-out visitor to login', function () {
    $this->get(route('leads.visibility-audit-recovery'))->assertRedirect(route('login'));
});

it('lists a lead stuck at checkout with no purchase', function () {
    $lead = Lead::factory()->create(['name' => 'Stuck Checkout Lead']);
    VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
        'tier' => VisibilityAuditTier::Gbp,
        'lead_id' => $lead->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('Stuck Checkout Lead');
});

it('lists a lead stuck at the landing page with no checkout and no purchase', function () {
    $lead = Lead::factory()->create(['name' => 'Stuck Landing Lead']);
    VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::LandingViewed,
        'lead_id' => $lead->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('Stuck Landing Lead');
});

it('does not list a lead who already completed a purchase', function () {
    $lead = Lead::factory()->create(['name' => 'Already Paid Lead']);
    VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
        'tier' => VisibilityAuditTier::Gbp,
        'lead_id' => $lead->id,
    ]);
    VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_test123',
        'lead_id' => $lead->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertDontSee('Already Paid Lead');
});

it('promotes a lead from the landing list to the checkout list once they reach checkout', function () {
    $lead = Lead::factory()->create(['name' => 'Progressed Lead']);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'tier' => VisibilityAuditTier::Gbp, 'lead_id' => $lead->id]);

    $metrics = app(VisibilityAuditFunnelMetrics::class);

    expect($metrics->stuckAtCheckout()->pluck('id'))->toContain($lead->id);
    expect($metrics->stuckAtLanding()->pluck('id'))->not->toContain($lead->id);
});

it('does not attribute an anonymous funnel event (no lead_id) to any lead', function () {
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => null]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'tier' => VisibilityAuditTier::Gbp, 'lead_id' => null]);

    $metrics = app(VisibilityAuditFunnelMetrics::class);

    expect($metrics->stuckAtLanding())->toBeEmpty();
    expect($metrics->stuckAtCheckout())->toBeEmpty();
});
