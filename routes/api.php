<?php

use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Middleware\VerifyLeadCaptureToken;
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
