<?php

use App\Enums\UserRole;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Livewire\VisibilityAuditActivitySummary;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditTouch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
});

it('lets an admin generate and dismiss the VA funnel activity summary', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "AFTER HOURS: Quiet overnight.\n\nTEAM ACTION NEEDED: Nothing outstanding."]],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(VisibilityAuditActivitySummary::class)
        ->call('generate')
        ->assertSet('summary', "AFTER HOURS: Quiet overnight.\n\nTEAM ACTION NEEDED: Nothing outstanding.")
        ->call('dismiss')
        ->assertSet('summary', null);
});

it('forbids a non-manager from generating the summary', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(VisibilityAuditActivitySummary::class)
        ->call('generate')
        ->assertForbidden();
});

it('hides the generate button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(VisibilityAuditActivitySummary::class)
        ->assertDontSee('AI Activity Summary');
});

it('feeds only out-of-office-hours touches into the after-hours section of the prompt', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'ok']],
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
    ])]);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $tz = 'Asia/Kolkata';

    $overnightLead = Lead::factory()->create(['name' => 'Overnight Lead']);
    $overnightTouch = VisibilityAuditTouch::create([
        'lead_id' => $overnightLead->id,
        'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp,
        'occurred_at' => now(),
        'success' => true,
    ]);
    $overnightTouch->forceFill(['occurred_at' => now($tz)->setTime(23, 0)->utc()])->saveQuietly();

    $daytimeLead = Lead::factory()->create(['name' => 'Daytime Lead']);
    $daytimeTouch = VisibilityAuditTouch::create([
        'lead_id' => $daytimeLead->id,
        'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp,
        'occurred_at' => now(),
        'success' => true,
    ]);
    $daytimeTouch->forceFill(['occurred_at' => now($tz)->setTime(12, 0)->utc()])->saveQuietly();

    Livewire::actingAs($admin)
        ->test(VisibilityAuditActivitySummary::class)
        ->call('generate');

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'Overnight Lead') && ! str_contains($body, 'Daytime Lead');
    });
});

it('flags the untagged-leads backlog, a stuck checkout lead, and an unanswered reply in the office-hours gap section', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'ok']],
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
    ])]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    // Untagged backlog.
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null, 'name' => 'Untagged Lead']);

    // Stuck at checkout, no staff reply since.
    $stuckLead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'name' => 'Stuck Checkout Lead']);
    $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $stuckLead->id]);
    $event->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();

    // Unanswered inbound reply.
    $repliedLead = Lead::factory()->create(['name' => 'Replied Lead']);
    VisibilityAuditTouch::create([
        'lead_id' => $repliedLead->id,
        'touch_type' => VisibilityAuditTouchType::CustomerReply,
        'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp,
        'occurred_at' => now()->subHours(3),
        'success' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(VisibilityAuditActivitySummary::class)
        ->call('generate');

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'Untagged Lead')
            && str_contains($body, 'Stuck Checkout Lead')
            && str_contains($body, 'Replied Lead');
    });
});

it('excludes a stuck lead that already got a staff WhatsApp reply from the gap section', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'ok']],
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
    ])]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $handledLead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'name' => 'Already Handled Lead']);
    $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $handledLead->id]);
    $event->forceFill(['created_at' => now()->subHours(6)])->saveQuietly();
    $handledLead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nCalling you shortly."]);

    Livewire::actingAs($admin)
        ->test(VisibilityAuditActivitySummary::class)
        ->call('generate');

    Http::assertSent(fn ($request) => ! str_contains($request->body(), 'Already Handled Lead'));
});
