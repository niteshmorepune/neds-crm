<?php

use App\Http\Controllers\Api\DrishtiWebhookController;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\SmdostWebhookController;
use App\Http\Controllers\Api\WhatsappWebhookController;
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

// nedsdrishti.in → CRM bridge. Receives post.approved / post.rejected /
// post.published events and writes them to the customer's activity feed.
// Auth: HMAC-SHA256 (X-Agency-Signature header) with DRISHTI_WEBHOOK_SECRET.
Route::post('/webhooks/drishti/event', [DrishtiWebhookController::class, 'handle'])
    ->middleware(['throttle:120,1', VerifyDrishtiWebhookSignature::class])
    ->name('api.webhooks.drishti.event');
