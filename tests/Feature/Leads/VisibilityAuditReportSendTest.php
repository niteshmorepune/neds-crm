<?php

use App\Enums\PaymentMode;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Jobs\SendVisibilityAuditReportEmailJob;
use App\Jobs\SendVisibilityAuditReportJob;
use App\Mail\VisibilityAuditReportEmail;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use App\Services\VisibilityAuditFunnelMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    Storage::fake('local');
    Queue::fake(); // LeadObserver's own side-effect jobs on Lead::factory()->create().
});

function reportPurchaseForLead(Lead $lead, array $overrides = []): VisibilityAuditPurchase
{
    return VisibilityAuditPurchase::create(array_merge([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_report_'.uniqid(),
        'payer_name' => $lead->name,
        'payer_phone' => '+91 98765 43210',
        'payer_email' => 'priya@shah.test',
        'lead_id' => $lead->id,
        'audit_ready_at' => now(),
    ], $overrides));
}

function heldMeetingFor(Lead $lead): Meeting
{
    return $lead->meetings()->create(Meeting::factory()->make(['occurred_at' => now()->subHour()])->toArray());
}

// ──────────────────────────────────────────────────────────────────────────────
// Model helpers
// ──────────────────────────────────────────────────────────────────────────────

it('generates a report token lazily, once, and reuses it', function () {
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    expect($purchase->report_token)->toBeNull();

    $url1 = $purchase->reportUrl();
    $token1 = $purchase->fresh()->report_token;
    expect($token1)->not->toBeNull();

    $url2 = $purchase->reportUrl();

    expect($url2)->toBe($url1)->and($purchase->fresh()->report_token)->toBe($token1);
});

it('reportAttachment() returns the newest uploaded attachment', function () {
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    $older = $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/old.pdf',
        'original_name' => 'old.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);
    $older->forceFill(['created_at' => now()->subMinute()])->saveQuietly();

    $newer = $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/new.pdf',
        'original_name' => 'new.pdf', 'mime_type' => 'application/pdf', 'size' => 200,
    ]);

    expect($purchase->reportAttachment()->id)->toBe($newer->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Public report link
// ──────────────────────────────────────────────────────────────────────────────

it('downloads the report via its public token', function () {
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);
    $file = UploadedFile::fake()->create('report.pdf', 50);
    $path = $file->store('attachments', 'local');
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => $path,
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => $file->getSize(),
    ]);

    $this->get($purchase->reportUrl())->assertOk();
});

it('404s the public report link for an unknown token', function () {
    $this->get(route('offers.visibility-audit.report', 'not-a-real-token'))->assertNotFound();
});

it('404s the public report link when no report has been uploaded yet', function () {
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    $this->get($purchase->reportUrl())->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Upload
// ──────────────────────────────────────────────────────────────────────────────

it('lets a manageMeetings-capable user upload the report file', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    $this->actingAs($sales)
        ->post(route('leads.visibility-audit.report.upload', [$lead, $purchase]), [
            'file' => UploadedFile::fake()->create('report.pdf', 100),
        ])
        ->assertRedirect();

    expect($purchase->reportAttachment())->not->toBeNull()
        ->and($purchase->reportAttachment()->original_name)->toBe('report.pdf');
});

it('forbids a Telecaller from uploading the report file', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    $this->actingAs($telecaller)
        ->post(route('leads.visibility-audit.report.upload', [$lead, $purchase]), [
            'file' => UploadedFile::fake()->create('report.pdf', 100),
        ])
        ->assertForbidden();
});

// ──────────────────────────────────────────────────────────────────────────────
// Send (controller)
// ──────────────────────────────────────────────────────────────────────────────

it('sends the report, updates report_sent_at/by, and dispatches both jobs, once the Gmeet has held and a file is uploaded', function () {
    Queue::fake();

    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);
    heldMeetingFor($lead);
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);

    $this->actingAs($sales)
        ->post(route('leads.visibility-audit.report.send', [$lead, $purchase]))
        ->assertRedirect();

    $purchase->refresh();
    expect($purchase->report_sent_at)->not->toBeNull()
        ->and($purchase->report_sent_by)->toBe($sales->id);

    Queue::assertPushed(SendVisibilityAuditReportJob::class, fn ($job) => $job->purchaseId === $purchase->id);
    Queue::assertPushed(SendVisibilityAuditReportEmailJob::class, fn ($job) => $job->purchaseId === $purchase->id);
});

it('allows a deliberate resend, dispatching both jobs again and refreshing report_sent_at', function () {
    Queue::fake();

    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead, ['report_sent_at' => now()->subDay()]);
    heldMeetingFor($lead);
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);
    $firstSentAt = $purchase->report_sent_at;

    $this->actingAs($sales)->post(route('leads.visibility-audit.report.send', [$lead, $purchase]))->assertRedirect();

    expect($purchase->fresh()->report_sent_at->gt($firstSentAt))->toBeTrue();
    Queue::assertPushed(SendVisibilityAuditReportJob::class, 1);
});

it('409s when the Gmeet has not been held yet', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);

    $this->actingAs($sales)
        ->post(route('leads.visibility-audit.report.send', [$lead, $purchase]))
        ->assertStatus(409);

    expect($purchase->fresh()->report_sent_at)->toBeNull();
});

it('409s when no report file has been uploaded yet, even with a held Gmeet', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);
    heldMeetingFor($lead);

    $this->actingAs($sales)
        ->post(route('leads.visibility-audit.report.send', [$lead, $purchase]))
        ->assertStatus(409);
});

// ──────────────────────────────────────────────────────────────────────────────
// SendVisibilityAuditReportJob — WhatsApp
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the report template with the report_token as buttonUrlParam', function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_report_template_name' => 'va_report',
    ]);
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1', 'messageId' => 'wamsg_1'], 201)]);

    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead, ['payer_name' => 'Priya Shah']);
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);

    (new SendVisibilityAuditReportJob($purchase->id))->handle();

    $purchase->refresh();
    expect($purchase->report_token)->not->toBeNull();

    Http::assertSent(function ($request) use ($purchase) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'va_report'
            && $request['variables'] === ['Priya Shah']
            && $request['buttonUrlParam'] === $purchase->report_token;
    });

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->touch_type)->toBe(VisibilityAuditTouchType::ReportSent)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiWhatsapp)
        ->and($touch->success)->toBeTrue();
});

it('skips the WhatsApp report send when no report file has been uploaded', function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_report_template_name' => 'va_report',
    ]);
    Http::fake();

    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    (new SendVisibilityAuditReportJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in is unreachable for the report send', function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_report_template_name' => 'va_report',
    ]);
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);

    expect(fn () => (new SendVisibilityAuditReportJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// SendVisibilityAuditReportEmailJob
// ──────────────────────────────────────────────────────────────────────────────

it('sends the report email with the file attached', function () {
    Mail::fake();

    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);
    $file = UploadedFile::fake()->create('report.pdf', 50);
    $path = $file->store('attachments', 'local');
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => $path,
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => $file->getSize(),
    ]);

    (new SendVisibilityAuditReportEmailJob($purchase->id))->handle();

    Mail::assertSent(VisibilityAuditReportEmail::class, function ($mail) use ($purchase) {
        return $mail->hasTo($purchase->payer_email)
            && $mail->hasAttachment(Attachment::fromStorageDisk('local', $purchase->reportAttachment()->path)->as('report.pdf')->withMime('application/pdf'));
    });

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->touch_type)->toBe(VisibilityAuditTouchType::ReportSent)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiEmail)
        ->and($touch->success)->toBeTrue();
});

it('skips the report email when no report file has been uploaded', function () {
    Mail::fake();

    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead);

    (new SendVisibilityAuditReportEmailJob($purchase->id))->handle();

    Mail::assertNothingSent();
});

it('skips the report email when the purchase has no payer email', function () {
    Mail::fake();

    $lead = Lead::factory()->create();
    $purchase = reportPurchaseForLead($lead, ['payer_email' => null]);
    $purchase->attachments()->create([
        'uploaded_by' => null, 'disk' => 'local', 'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
    ]);

    (new SendVisibilityAuditReportEmailJob($purchase->id))->handle();

    Mail::assertNothingSent();
});

// ──────────────────────────────────────────────────────────────────────────────
// funnelStatusFor()
// ──────────────────────────────────────────────────────────────────────────────

it('shows "report_sent" once the report has gone out, outranking gmeet_held', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);
    $purchase = reportPurchaseForLead($lead, ['report_sent_at' => now()]);
    heldMeetingFor($lead);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('report_sent')->and($status['purchase_id'])->toBe($purchase->id);
});

it('shows "quotation_sent" once a converted deal has a sent quotation, outranking report_sent', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);
    reportPurchaseForLead($lead, ['report_sent_at' => now()]);
    $deal = Deal::factory()->create();
    $lead->update(['converted_deal_id' => $deal->id]);
    $quotation = Quotation::factory()->create([
        'deal_id' => $deal->id,
        'status' => QuotationStatus::Sent,
        'number' => 'NEDS/2026-27/0099',
    ]);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('quotation_sent')
        ->and($status['quotation_id'])->toBe($quotation->id);
});

it('does not show quotation_sent for a Draft quotation', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);
    $purchase = reportPurchaseForLead($lead, ['report_sent_at' => now()]);
    $deal = Deal::factory()->create();
    $lead->update(['converted_deal_id' => $deal->id]);
    Quotation::factory()->create(['deal_id' => $deal->id, 'status' => QuotationStatus::Draft]);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('report_sent')->and($status['purchase_id'])->toBe($purchase->id);
});

it('shows "advance_paid" once a milestone invoice on the converted deal\'s quotation has a payment, outranking quotation_sent', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);
    reportPurchaseForLead($lead, ['report_sent_at' => now()]);
    $deal = Deal::factory()->create();
    $lead->update(['converted_deal_id' => $deal->id]);
    $quotation = Quotation::factory()->create([
        'deal_id' => $deal->id,
        'status' => QuotationStatus::Sent,
        'number' => 'NEDS/2026-27/0100',
    ]);
    $invoice = Invoice::factory()->create(['quotation_id' => $quotation->id, 'total' => 59000, 'amount_paid' => 59000]);
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 50, 'amount' => 59000, 'sort_order' => 0, 'invoice_id' => $invoice->id]);
    $invoice->payments()->create(['paid_on' => now()->toDateString(), 'mode' => PaymentMode::Gateway, 'amount' => 59000, 'tds_amount' => 0]);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('advance_paid')
        ->and($status['quotation_id'])->toBe($quotation->id)
        ->and($status['label'])->toContain($milestone->title);
});

it('falls back to the purchase-based ladder when the converted deal has no quotations yet', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);
    $purchase = reportPurchaseForLead($lead, ['report_sent_at' => now()]);
    $deal = Deal::factory()->create();
    $lead->update(['converted_deal_id' => $deal->id]);

    $status = app(VisibilityAuditFunnelMetrics::class)->funnelStatusFor($lead);

    expect($status['stage'])->toBe('report_sent')->and($status['purchase_id'])->toBe($purchase->id);
});
