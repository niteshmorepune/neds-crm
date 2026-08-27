<?php

use App\Enums\UserRole;
use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditPurchase;
use App\Notifications\VisibilityAuditReadyForGmeet;
use App\Services\VisibilityAuditFunnelMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    Queue::fake(); // LeadObserver's own side-effect jobs on Lead::factory()->create().
});

function vaPurchaseForLead(Lead $lead, array $overrides = []): VisibilityAuditPurchase
{
    return VisibilityAuditPurchase::create(array_merge([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_gate_'.uniqid(),
        'payer_name' => $lead->name,
        'lead_id' => $lead->id,
    ], $overrides));
}

/**
 * funnelStatusFor()/the Lead page's VA badge only render for a lead in the
 * VA cohort (isVisibilityAuditCohort()) — meta_leadgen_id + GMB service,
 * same eligibility rule as everywhere else in this pipeline.
 */
function vaCohortLead(array $overrides = []): Lead
{
    $gmb = Service::where('name', 'GMB')->first() ?? Service::factory()->create(['name' => 'GMB', 'is_active' => true]);

    return Lead::factory()->create(array_merge([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $gmb->id,
    ], $overrides));
}

// ──────────────────────────────────────────────────────────────────────────────
// VisibilityAuditPurchase::hasHeldGmeet()
// ──────────────────────────────────────────────────────────────────────────────

it('reports no held Gmeet when the lead has no meetings at all', function () {
    $lead = Lead::factory()->create();
    $purchase = vaPurchaseForLead($lead);

    expect($purchase->hasHeldGmeet())->toBeFalse();
});

it('reports no held Gmeet when a meeting is scheduled in the future', function () {
    $lead = Lead::factory()->create();
    $purchase = vaPurchaseForLead($lead);
    $lead->meetings()->create(Meeting::factory()->make(['occurred_at' => now()->addDay()])->toArray());

    expect($purchase->hasHeldGmeet())->toBeFalse();
});

it('reports a held Gmeet once a meeting\'s scheduled time has passed', function () {
    $lead = Lead::factory()->create();
    $purchase = vaPurchaseForLead($lead);
    $lead->meetings()->create(Meeting::factory()->make(['occurred_at' => now()->subHour()])->toArray());

    expect($purchase->hasHeldGmeet())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// VisibilityAuditFunnelMetrics::funnelStatusFor() — the 2 new stages
// ──────────────────────────────────────────────────────────────────────────────

it('shows "paid" with a purchase_id when the audit is not yet marked ready', function () {
    $lead = vaCohortLead();
    $purchase = vaPurchaseForLead($lead);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('paid')->and($status['purchase_id'])->toBe($purchase->id);
});

it('shows "ready_awaiting_gmeet" once marked ready but no Gmeet held yet', function () {
    $lead = vaCohortLead();
    $purchase = vaPurchaseForLead($lead, ['audit_ready_at' => now()]);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('ready_awaiting_gmeet')->and($status['purchase_id'])->toBe($purchase->id);
});

it('shows "gmeet_held" once a Gmeet has actually happened after marking ready', function () {
    $lead = vaCohortLead();
    $purchase = vaPurchaseForLead($lead, ['audit_ready_at' => now()]);
    $lead->meetings()->create(Meeting::factory()->make(['occurred_at' => now()->subHour()])->toArray());

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('gmeet_held');
});

it('still shows "ready_awaiting_gmeet" when a Gmeet is scheduled but hasn\'t happened yet', function () {
    $lead = vaCohortLead();
    vaPurchaseForLead($lead, ['audit_ready_at' => now()]);
    $lead->meetings()->create(Meeting::factory()->make(['occurred_at' => now()->addDay()])->toArray());

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('ready_awaiting_gmeet');
});

// ──────────────────────────────────────────────────────────────────────────────
// LeadController::markVisibilityAuditReady()
// ──────────────────────────────────────────────────────────────────────────────

it('marks the audit ready, records who, and notifies the lead\'s owner', function () {
    Notification::fake();

    $owner = User::factory()->role(UserRole::Sales)->create();
    $staff = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);
    $purchase = vaPurchaseForLead($lead);

    $this->actingAs($staff)
        ->post(route('leads.visibility-audit.ready', [$lead, $purchase]))
        ->assertRedirect();

    $purchase->refresh();
    expect($purchase->audit_ready_at)->not->toBeNull()
        ->and($purchase->audit_ready_by)->toBe($staff->id);

    Notification::assertSentTo($owner, VisibilityAuditReadyForGmeet::class, fn ($n) => $n->purchase->is($purchase));
});

it('does not re-notify or overwrite audit_ready_at on a second call', function () {
    Notification::fake();

    $owner = User::factory()->role(UserRole::Sales)->create();
    $staff = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);
    $purchase = vaPurchaseForLead($lead);

    $this->actingAs($staff)->post(route('leads.visibility-audit.ready', [$lead, $purchase]));
    $firstReadyAt = $purchase->fresh()->audit_ready_at;

    $this->actingAs($staff)->post(route('leads.visibility-audit.ready', [$lead, $purchase]));

    expect($purchase->fresh()->audit_ready_at->eq($firstReadyAt))->toBeTrue();
    Notification::assertSentToTimes($owner, VisibilityAuditReadyForGmeet::class, 1);
});

it('does not notify anyone when the lead has no owner', function () {
    Notification::fake();

    $staff = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['owner_id' => null]);
    $purchase = vaPurchaseForLead($lead);

    $this->actingAs($staff)
        ->post(route('leads.visibility-audit.ready', [$lead, $purchase]))
        ->assertRedirect();

    expect($purchase->fresh()->audit_ready_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('404s when the purchase does not belong to the lead in the URL', function () {
    $staff = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create();
    $otherLead = Lead::factory()->create();
    $purchase = vaPurchaseForLead($otherLead);

    $this->actingAs($staff)
        ->post(route('leads.visibility-audit.ready', [$lead, $purchase]))
        ->assertNotFound();
});

it('allows a Sales user (manageMeetings) even without owning the lead, but forbids Telecaller', function () {
    $lead = Lead::factory()->create(); // no owner_id -- proves ownership isn't the gate, manageMeetings() is.
    $purchase = vaPurchaseForLead($lead);

    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $this->actingAs($telecaller)
        ->post(route('leads.visibility-audit.ready', [$lead, $purchase]))
        ->assertForbidden();

    $sales = User::factory()->role(UserRole::Sales)->create();
    $this->actingAs($sales)
        ->post(route('leads.visibility-audit.ready', [$lead, $purchase]))
        ->assertRedirect();
});

/**
 * A Support user technically passes LeadPolicy::manageMeetings(), but
 * never reaches this (or any) Lead route at all — Support isn't a default
 * role for the lead-generation menu key (MenuItemsSeeder), so
 * `menu.access:lead-generation` middleware 403s first. Same pre-existing
 * behaviour the Create Meeting button on a Lead page already has (it uses
 * the identical manageMeetings() gate) -- not a regression introduced
 * here, just documented so a future session doesn't mistake it for one.
 */
it('403s a Support user before the controller/Policy is ever reached, via menu access', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $lead = Lead::factory()->create();
    $purchase = vaPurchaseForLead($lead);

    $this->actingAs($support)
        ->post(route('leads.visibility-audit.ready', [$lead, $purchase]))
        ->assertForbidden();
});

// ──────────────────────────────────────────────────────────────────────────────
// Lead show page — the button itself
// ──────────────────────────────────────────────────────────────────────────────

it('shows the "Mark audit ready" button on a paid, not-yet-ready purchase for a manageMeetings-capable viewer', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = vaCohortLead();
    vaPurchaseForLead($lead);

    $this->actingAs($sales)
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Mark audit ready');
});

it('hides the "Mark audit ready" button once already marked ready', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = vaCohortLead();
    vaPurchaseForLead($lead, ['audit_ready_at' => now()]);

    $this->actingAs($sales)
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Mark audit ready')
        ->assertSee('Gmeet not scheduled/held yet', false);
});
