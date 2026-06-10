<?php

use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Middleware\VerifyLeadCaptureToken;
use Illuminate\Support\Facades\Route;

// Public lead capture for the company website form. Token-protected, stateless
// (no CSRF/session). POST /api/leads with an Authorization: Bearer <token>
// or X-Lead-Token header.
Route::post('/leads', [LeadCaptureController::class, 'store'])
    ->middleware(VerifyLeadCaptureToken::class)
    ->name('api.leads.store');
