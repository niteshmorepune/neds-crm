<?php

use App\Http\Controllers\Api\BiometricWebhookController;
use App\Http\Controllers\Api\DrishtiWebhookController;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\SmdostWebhookController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Middleware\VerifyBiometricDeviceSerial;
use App\Http\Middleware\VerifyDrishtiWebhookSignature;
use App\Http\Middleware\VerifyLeadCaptureToken;
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

// eSSL biometric device ADMS push. The device is configured with:
//   Server Address: crm.talktonitesh.com, Port: 443, HTTPS: ON
//   Auth: SN query param validated against BIOMETRIC_DEVICE_SERIAL.
// GET = device ping/registration handshake; POST = attendance log push.
Route::middleware(['throttle:300,1', VerifyBiometricDeviceSerial::class])
    ->prefix('iclock')
    ->group(function () {
        Route::get('/cdata', [BiometricWebhookController::class, 'ping'])
            ->name('api.biometric.ping');
        Route::post('/cdata', [BiometricWebhookController::class, 'push'])
            ->name('api.biometric.push');
    });

// nedsdrishti.in → CRM bridge. Receives post.approved / post.rejected /
// post.published events and writes them to the customer's activity feed.
// Auth: HMAC-SHA256 (X-Agency-Signature header) with DRISHTI_WEBHOOK_SECRET.
Route::post('/webhooks/drishti/event', [DrishtiWebhookController::class, 'handle'])
    ->middleware(['throttle:120,1', VerifyDrishtiWebhookSignature::class])
    ->name('api.webhooks.drishti.event');
