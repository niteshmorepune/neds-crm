<?php

use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Controllers\Api\DrishtiTrendIdeaBriefController;
use App\Http\Controllers\Api\DrishtiWebhookController;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\MetaLeadsWebhookController;
use App\Http\Controllers\Api\RazorpayVisibilityAuditWebhookController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\Api\SmdostWebhookController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Middleware\VerifyBiometricBridgeToken;
use App\Http\Middleware\VerifyDrishtiWebhookSignature;
use App\Http\Middleware\VerifyLeadCaptureToken;
use App\Http\Middleware\VerifyMetaWebhookSignature;
use App\Http\Middleware\VerifyRazorpayVisibilityAuditWebhookSignature;
use App\Http\Middleware\VerifyRazorpayWebhookSignature;
use App\Http\Middleware\VerifySmdostWebhookToken;
use App\Http\Middleware\VerifyWhatsappWebhookToken;
use Illuminate\Support\Facades\Route;

// Public lead capture for the company website form. Token-protected, stateless
// (no CSRF/session). POST /api/leads with an Authorization: Bearer <token>
// or X-Lead-Token header.
Route::post('/leads', [LeadCaptureController::class, 'store'])
    ->middleware(['throttle:30,1', VerifyLeadCaptureToken::class])
    ->name('api.leads.store');

// WhatsApp → CRM bridge. Called by wadesk.in when a new/reopened conversation
// starts. Creates a CRM Ticket for the matching customer. Bearer token auth.
Route::post('/webhook/whatsapp', [WhatsappWebhookController::class, 'handle'])
    ->middleware(['throttle:60,1', VerifyWhatsappWebhookToken::class])
    ->name('api.webhook.whatsapp');

// socialmediadost.com → CRM bridge. Called when all content in a brief is
// approved. Creates a draft invoice for the accounts team to price and send.
Route::post('/webhooks/smdost/brief-approved', [SmdostWebhookController::class, 'briefApproved'])
    ->middleware(['throttle:60,1', VerifySmdostWebhookToken::class])
    ->name('api.webhooks.smdost.brief-approved');

// socialmediadost.com → CRM bridge. Called when a content piece's copy is
// ready to send to the partner agency for creative (images/video). Creates
// a neds_led ContentPiece (sent_to_partner) so the team can track it.
Route::post('/webhooks/smdost/content-ready', [SmdostWebhookController::class, 'contentReady'])
    ->middleware(['throttle:60,1', VerifySmdostWebhookToken::class])
    ->name('api.webhooks.smdost.content-ready');

// nedsdrishti.in → CRM bridge. Receives post.approved / post.rejected /
// post.published events and writes them to the customer's activity feed.
// Auth: HMAC-SHA256 (X-Agency-Signature header) with DRISHTI_WEBHOOK_SECRET.
Route::post('/webhooks/drishti/event', [DrishtiWebhookController::class, 'handle'])
    ->middleware(['throttle:120,1', VerifyDrishtiWebhookSignature::class])
    ->name('api.webhooks.drishti.event');

// nedsdrishti.in → CRM bridge. Called when staff click "Send to SMDost" on an
// approved AI trend idea. The CRM is the only place that knows both a
// client's drishti_client_id and smdost_client_id, so it looks up the
// matching customer and forwards a brief-creation call to SMDost's existing
// /api/briefs endpoint (same one CreateMonthlyBriefs already calls).
Route::post('/webhooks/drishti/trend-idea-brief', [DrishtiTrendIdeaBriefController::class, 'store'])
    ->middleware(['throttle:60,1', VerifyDrishtiWebhookSignature::class])
    ->name('api.webhooks.drishti.trend-idea-brief');

// Meta (Facebook/Instagram) Lead Ads webhook. Meta calls GET once to verify
// the subscription (hub.verify_token, no signature involved — the token
// match IS the auth) and POST on every new lead (X-Hub-Signature-256,
// verified by VerifyMetaWebhookSignature). The POST payload only carries a
// leadgen_id; ImportMetaLead fetches the actual field data via the Graph API.
Route::get('/webhooks/meta-leads', [MetaLeadsWebhookController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('api.webhooks.meta-leads.verify');
Route::post('/webhooks/meta-leads', [MetaLeadsWebhookController::class, 'receive'])
    ->middleware(['throttle:120,1', VerifyMetaWebhookSignature::class])
    ->name('api.webhooks.meta-leads.receive');

// Razorpay → CRM bridge. Client Portal "Pay Now" (online invoice payment).
// payment.captured events confirm a payment made against an order created by
// Portal\InvoicePaymentController::order() and record it via a queued job —
// the reliable/authoritative path alongside the portal's own synchronous
// verify callback (immediate UX; both share RazorpayPaymentRecorder's
// gateway_payment_id idempotency guard, so whichever fires first wins).
// Auth: HMAC-SHA256 (X-Razorpay-Signature header) with RAZORPAY_WEBHOOK_SECRET
// — a separate webhook subscription/secret from the one already configured
// for niranjanenterprises.com in the same Razorpay account.
Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])
    ->middleware(['throttle:120,1', VerifyRazorpayWebhookSignature::class])
    ->name('api.webhooks.razorpay');

// Razorpay → CRM bridge. Visibility Audit offer (/offers/visibility-audit)
// Payment Page purchases. A completed payment is matched to a Lead by phone
// (Lead::findOpenByPhone(), same dedup mechanism ImportMetaLead uses) and
// logged as a note — deliberately does not auto-create a Deal, see
// RecordVisibilityAuditPurchase. A separate webhook subscription/secret
// from the one above, so this can never be confused with an invoice payment.
Route::post('/webhooks/razorpay/visibility-audit', [RazorpayVisibilityAuditWebhookController::class, 'handle'])
    ->middleware(['throttle:120,1', VerifyRazorpayVisibilityAuditWebhookSignature::class])
    ->name('api.webhooks.razorpay.visibility-audit');

// Office-LAN bridge (tools/biometric-bridge/check-manual-sync.mjs) polls
// this once a minute to see if an admin/manager has clicked "Sync now" on
// the Attendance page, then reports back when it's done. The CRM can't
// reach the device directly (office LAN only), so this request/poll flag
// is the only way to shrink the wait below the normal 5-minute schedule
// without exposing the office PC to the internet. Bearer/X-Bridge-Token
// auth via BIOMETRIC_BRIDGE_TOKEN — separate secret from the device's own
// BIOMETRIC_DEVICE_SERIAL.
Route::get('/biometric-sync/pending', [BiometricSyncController::class, 'pending'])
    ->middleware(['throttle:120,1', VerifyBiometricBridgeToken::class])
    ->name('api.biometric-sync.pending');
Route::post('/biometric-sync/{syncRequest}/complete', [BiometricSyncController::class, 'complete'])
    ->middleware(['throttle:120,1', VerifyBiometricBridgeToken::class])
    ->name('api.biometric-sync.complete');
