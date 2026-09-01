<?php

use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Jobs\SendVisibilityAuditInProgressEmailJob;
use App\Jobs\SendVisibilityAuditInProgressJob;
use App\Mail\VisibilityAuditInProgressEmail;
use App\Models\Lead;
use App\Models\User;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_in_progress_template_name' => 'va_in_progress',
    ]);
});

function inProgressPurchase(array $overrides = []): VisibilityAuditPurchase
{
    return VisibilityAuditPurchase::create(array_merge([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_ip_'.uniqid(),
        'payer_name' => 'Priya Shah',
        'payer_phone' => '+91 98765 43210',
        'payer_email' => 'priya@shah.test',
    ], $overrides));
}

// ──────────────────────────────────────────────────────────────────────────────
// SendVisibilityAuditInProgressJob — WhatsApp
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the template and marks the purchase in-progress-notified', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1', 'messageId' => 'wamsg_1'], 201)]);

    $lead = Lead::factory()->create();
    $purchase = inProgressPurchase(['lead_id' => $lead->id]);

    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'va_in_progress'
            && $request['variables'] === ['Priya Shah'];
    });

    expect($purchase->fresh()->in_progress_notified_at)->not->toBeNull();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::AuditInProgress)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiWhatsapp)
        ->and($touch->success)->toBeTrue();
});

it('logs no touch for an anonymous purchase with no matched lead', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $purchase = inProgressPurchase(['lead_id' => null]);

    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();

    expect(VisibilityAuditTouch::count())->toBe(0);
    expect($purchase->fresh()->in_progress_notified_at)->not->toBeNull();
});

it('logs a failed touch when wadesk.in returns non-2xx, and counts the attempt without giving up yet', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'bad'], 500)]);

    $lead = Lead::factory()->create();
    $purchase = inProgressPurchase(['lead_id' => $lead->id]);

    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->success)->toBeFalse();
    $fresh = $purchase->fresh();
    expect($fresh->in_progress_notified_at)->toBeNull()
        ->and($fresh->in_progress_whatsapp_attempts)->toBe(1)
        ->and($fresh->in_progress_whatsapp_gave_up_at)->toBeNull();
});

it('gives up after MAX_ATTEMPTS consecutive failures and stops attempting the HTTP call', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'Template not found or not approved'], 404)]);

    $purchase = inProgressPurchase();

    foreach (range(1, 5) as $attempt) {
        (new SendVisibilityAuditInProgressJob($purchase->id))->handle();
    }

    $fresh = $purchase->fresh();
    expect($fresh->in_progress_whatsapp_attempts)->toBe(5)
        ->and($fresh->in_progress_whatsapp_gave_up_at)->not->toBeNull();

    Http::fake(); // reset the recorded request count
    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();
    Http::assertNothingSent(); // 6th call never even attempts the HTTP request

    expect($purchase->fresh()->in_progress_whatsapp_attempts)->toBe(5); // unchanged
});

it('never sends twice once already in-progress-notified', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $purchase = inProgressPurchase(['in_progress_notified_at' => now()]);

    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the template name is not configured', function () {
    Http::fake();
    config(['services.wadesk.visibility_audit_in_progress_template_name' => null]);

    $purchase = inProgressPurchase();

    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();

    Http::assertNothingSent();
    expect($purchase->fresh()->in_progress_notified_at)->toBeNull();
});

it('skips the HTTP call when the purchase has no payer phone', function () {
    Http::fake();

    $purchase = inProgressPurchase(['payer_phone' => null]);

    (new SendVisibilityAuditInProgressJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $purchase = inProgressPurchase();

    expect(fn () => (new SendVisibilityAuditInProgressJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// SendVisibilityAuditInProgressEmailJob — email sibling
// ──────────────────────────────────────────────────────────────────────────────

it('sends the in-progress email and marks the purchase in-progress-emailed', function () {
    Mail::fake();

    $lead = Lead::factory()->create();
    $purchase = inProgressPurchase(['lead_id' => $lead->id]);

    (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle();

    Mail::assertSent(VisibilityAuditInProgressEmail::class, fn ($mail) => $mail->hasTo($purchase->payer_email) && $mail->purchase->is($purchase));

    expect($purchase->fresh()->in_progress_notified_email_at)->not->toBeNull();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::AuditInProgress)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiEmail)
        ->and($touch->success)->toBeTrue();
});

it('sets Reply-To and CCs the matched lead\'s owner on the in-progress email', function () {
    Mail::fake();
    config(['company.reply_to_email' => 'contact@niranjanenterprises.com']);

    $owner = User::factory()->create(['email' => 'kiran@niranjanenterprises.co.in']);
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);
    $purchase = inProgressPurchase(['lead_id' => $lead->id]);

    (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle();

    Mail::assertSent(VisibilityAuditInProgressEmail::class, fn ($mail) => $mail->hasReplyTo('contact@niranjanenterprises.com')
        && $mail->hasCc('kiran@niranjanenterprises.co.in'));
});

it('skips the in-progress email when the purchase has no payer email', function () {
    Mail::fake();

    $purchase = inProgressPurchase(['payer_email' => null]);

    (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle();

    Mail::assertNothingSent();
});

it('never emails twice once already in-progress-emailed', function () {
    Mail::fake();

    $purchase = inProgressPurchase(['in_progress_notified_email_at' => now()]);

    (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle();

    Mail::assertNothingSent();
});

it('logs a failed touch but does not throw when the in-progress email send fails', function () {
    Mail::shouldReceive('to')->andThrow(new Exception('SMTP down'));

    $lead = Lead::factory()->create();
    $purchase = inProgressPurchase(['lead_id' => $lead->id]);

    expect(fn () => (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle())->not->toThrow(Throwable::class);

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->success)->toBeFalse()->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiEmail);
    expect($purchase->fresh()->in_progress_notified_email_at)->toBeNull();
});

it('gives up on the email channel after MAX_ATTEMPTS consecutive failures and stops attempting the send', function () {
    Mail::shouldReceive('to')->andThrow(new Exception('SMTP down'));

    $purchase = inProgressPurchase();

    foreach (range(1, 5) as $attempt) {
        (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle();
    }

    $fresh = $purchase->fresh();
    expect($fresh->in_progress_email_attempts)->toBe(5)
        ->and($fresh->in_progress_email_gave_up_at)->not->toBeNull();

    // 6th call: the guard must return before ever calling Mail::to() again,
    // so the still-throwing mock above is never re-triggered and the
    // attempt counter stays put.
    (new SendVisibilityAuditInProgressEmailJob($purchase->id))->handle();

    expect($purchase->fresh()->in_progress_email_attempts)->toBe(5); // unchanged
});

it('skips the in-progress email when the purchase no longer exists', function () {
    Mail::fake();

    (new SendVisibilityAuditInProgressEmailJob(999999))->handle();

    Mail::assertNothingSent();
});

// ──────────────────────────────────────────────────────────────────────────────
// Metrics: which purchases are actually pending
// ──────────────────────────────────────────────────────────────────────────────

it('only surfaces a purchase as pending once past the wait threshold', function () {
    $recent = inProgressPurchase();
    $recent->created_at = now()->subMinutes(10);
    $recent->save();

    $old = inProgressPurchase();
    $old->created_at = now()->subHours(1);
    $old->save();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingInProgressNudges(now()->subMinutes(30))->pluck('id');

    expect($pending)->not->toContain($recent->id)->toContain($old->id);
});

it('still surfaces a purchase whose WhatsApp side already went out but email did not', function () {
    $purchase = inProgressPurchase(['in_progress_notified_at' => now()]);
    $purchase->created_at = now()->subHours(1);
    $purchase->save();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingInProgressNudges(now()->subMinutes(30))->pluck('id');

    expect($pending)->toContain($purchase->id);
});

it('excludes a purchase once both channels are done', function () {
    $purchase = inProgressPurchase(['in_progress_notified_at' => now(), 'in_progress_notified_email_at' => now()]);
    $purchase->created_at = now()->subHours(1);
    $purchase->save();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingInProgressNudges(now()->subMinutes(30))->pluck('id');

    expect($pending)->not->toContain($purchase->id);
});

it('still surfaces a purchase for its email retry once WhatsApp has given up', function () {
    $purchase = inProgressPurchase(['in_progress_whatsapp_gave_up_at' => now()]);
    $purchase->created_at = now()->subHours(1);
    $purchase->save();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingInProgressNudges(now()->subMinutes(30))->pluck('id');

    expect($pending)->toContain($purchase->id);
});

it('excludes a purchase once WhatsApp has given up and email already went out', function () {
    $purchase = inProgressPurchase([
        'in_progress_whatsapp_gave_up_at' => now(),
        'in_progress_notified_email_at' => now(),
    ]);
    $purchase->created_at = now()->subHours(1);
    $purchase->save();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingInProgressNudges(now()->subMinutes(30))->pluck('id');

    expect($pending)->not->toContain($purchase->id);
});

it('excludes a purchase once both channels have given up', function () {
    $purchase = inProgressPurchase([
        'in_progress_whatsapp_gave_up_at' => now(),
        'in_progress_email_gave_up_at' => now(),
    ]);
    $purchase->created_at = now()->subHours(1);
    $purchase->save();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingInProgressNudges(now()->subMinutes(30))->pluck('id');

    expect($pending)->not->toContain($purchase->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Command
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches both jobs for every pending purchase when the command runs', function () {
    Queue::fake();

    $pending = inProgressPurchase();
    $pending->created_at = now()->subHours(1);
    $pending->save();

    $tooRecent = inProgressPurchase();

    Artisan::call('app:send-visibility-audit-in-progress-nudges');

    Queue::assertPushed(SendVisibilityAuditInProgressJob::class, fn ($job) => $job->purchaseId === $pending->id);
    Queue::assertPushed(SendVisibilityAuditInProgressEmailJob::class, fn ($job) => $job->purchaseId === $pending->id);
    Queue::assertNotPushed(SendVisibilityAuditInProgressJob::class, fn ($job) => $job->purchaseId === $tooRecent->id);
});
