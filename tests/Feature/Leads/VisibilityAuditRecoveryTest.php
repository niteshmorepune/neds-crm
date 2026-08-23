<?php

use App\Enums\UserRole;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
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
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id, 'visibility_audit_invited_at' => now()]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);

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

// ──────────────────────────────────────────────────────────────────────────────
// "Your gaps" / "Your message log" — personal, owner-scoped sections
// ──────────────────────────────────────────────────────────────────────────────

it('shows a Sales user their own stuck-at-checkout lead in "Your gaps"', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $mine = Lead::factory()->create(['name' => 'My Stuck Lead', 'owner_id' => $sales->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $mine->id]);

    $this->actingAs($sales)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('My Stuck Lead')
        ->assertSee('Reached checkout, hasn\'t paid — call them', false);
});

it('excludes a "your gaps" lead already handled by a staff WhatsApp reply', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $handled = Lead::factory()->create(['name' => 'Handled By Me', 'owner_id' => $sales->id]);
    $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $handled->id]);
    $event->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();
    $handled->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by {$sales->name}]\nCalling you now."]);

    $response = $this->actingAs($sales)->get(route('leads.visibility-audit-recovery'))->assertOk();
    $response->assertSee('Nothing outstanding on your own leads.');
});

it('shows a Sales user their own untagged Meta lead in "Your gaps"', function () {
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['name' => 'My Untagged Lead', 'owner_id' => $sales->id, 'meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);

    $this->actingAs($sales)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('My Untagged Lead')
        ->assertSee('Tag a service');
});

it('shows a Sales user their own AI-WhatsApp sends in "Your message log", scoped to their own leads', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();

    $mine = Lead::factory()->create(['name' => 'My Log Lead', 'owner_id' => $sales->id]);
    VisibilityAuditTouch::create([
        'lead_id' => $mine->id,
        'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp,
        'occurred_at' => now(),
        'success' => true,
    ]);

    $theirs = Lead::factory()->create(['name' => 'Their Log Lead', 'owner_id' => $otherSales->id]);
    VisibilityAuditTouch::create([
        'lead_id' => $theirs->id,
        'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp,
        'occurred_at' => now(),
        'success' => true,
    ]);

    $this->actingAs($sales)
        ->get(route('leads.visibility-audit-recovery'))
        ->assertOk()
        ->assertSee('My Log Lead')
        ->assertDontSee('Their Log Lead');
});
