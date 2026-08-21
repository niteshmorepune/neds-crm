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
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('redirects a guest to login', function () {
    $this->get(route('reports.visibility-audit-funnel'))->assertRedirect('/login');
});

it('forbids a Sales user', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('reports.visibility-audit-funnel'))->assertForbidden();
});

it('renders the dashboard for a Manager with real data', function () {
    Queue::fake();
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id, 'visibility_audit_invited_at' => now()]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_dash1', 'lead_id' => $lead->id]);

    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel'))
        ->assertOk()
        ->assertSee('Visibility Audit Funnel Dashboard')
        ->assertSee('Eligible leads');
});

it('renders for an Admin too', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('reports.visibility-audit-funnel'))->assertOk();
});

it('renders cleanly with zero data in the window', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel'))->assertOk();
});

it('flags Meta leads awaiting a service tag', function () {
    Queue::fake();
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel'))
        ->assertOk()
        ->assertSee('no service tagged yet');
});

it('respects a custom date range', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel', [
        'from' => now()->subDays(60)->toDateString(),
        'to' => now()->subDays(45)->toDateString(),
    ]))->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// leads() — the stage drill-down
// ──────────────────────────────────────────────────────────────────────────────

it('redirects a guest away from the drill-down', function () {
    $this->get(route('reports.visibility-audit-funnel.leads', ['stage' => 'landing_viewed']))->assertRedirect('/login');
});

it('forbids a Sales user from the drill-down', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('reports.visibility-audit-funnel.leads', ['stage' => 'landing_viewed']))->assertForbidden();
});

it('404s on an unknown stage', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel.leads', ['stage' => 'bogus']))->assertNotFound();
});

it('lists the leads behind a stage for a Manager', function () {
    Queue::fake();
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id, 'visibility_audit_invited_at' => now(), 'name' => 'Drilldown Test Lead']);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);

    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel.leads', ['stage' => 'landing_viewed']))
        ->assertOk()
        ->assertSee('Viewed offer page')
        ->assertSee('Drilldown Test Lead');
});

it('lists leads not yet invited via the same drill-down', function () {
    Queue::fake();
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id, 'name' => 'Not Invited Yet Lead']);

    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel.leads', ['stage' => 'not_invited']))
        ->assertOk()
        ->assertSee('Not Invited Yet Lead');
});

// ──────────────────────────────────────────────────────────────────────────────
// messages() — the AI-WhatsApp send log
// ──────────────────────────────────────────────────────────────────────────────

it('redirects a guest away from the message log', function () {
    $this->get(route('reports.visibility-audit-funnel.messages'))->assertRedirect('/login');
});

it('forbids a Sales user from the message log', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('reports.visibility-audit-funnel.messages'))->assertForbidden();
});

it('lists AI-WhatsApp send attempts with lead/type/outcome for a Manager', function () {
    $lead = Lead::factory()->create(['name' => 'Message Log Lead']);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::RecoveryNudgeLanding, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => false, 'meta' => ['error' => 'wadesk timeout']]);

    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel.messages'))
        ->assertOk()
        ->assertSee('Message Log Lead')
        ->assertSee('First invite')
        ->assertSee('wadesk timeout');
});

it('filters the message log by outcome', function () {
    $lead = Lead::factory()->create(['name' => 'Filter Test Lead']);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);

    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('reports.visibility-audit-funnel.messages', ['outcome' => 'failed']))
        ->assertOk()
        ->assertDontSee('Filter Test Lead');
});
