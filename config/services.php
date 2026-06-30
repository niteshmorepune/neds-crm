<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Shared secret for the public website lead-capture API endpoint.
    'lead_capture' => [
        'token' => env('LEAD_CAPTURE_TOKEN'),
    ],

    // Shared secret for the wadesk.in → CRM WhatsApp webhook (inbound).
    'whatsapp_webhook' => [
        'token' => env('WHATSAPP_WEBHOOK_TOKEN'),
    ],

    // wadesk.in — WhatsApp conversation platform (outbound staff replies).
    // WADESK_SERVICE_KEY must match the WADESK_SERVICE_KEY set in wadesk.in's .env.
    'wadesk' => [
        'base_url' => env('WADESK_API_URL', 'https://wadesk.in'),
        'service_key' => env('WADESK_SERVICE_KEY'),
    ],

    // nedsdrishti.in — agency service delivery platform.
    // The CRM provisions clients here when a deal is won.
    // webhook_secret: the per-webhook HMAC secret Drishti generated when we
    // registered the CRM as a webhook subscriber (GET /api/webhooks → secret field).
    'drishti' => [
        'base_url' => env('DRISHTI_API_URL', 'https://nedsdrishti.in'),
        'service_key' => env('DRISHTI_SERVICE_KEY'),
        'webhook_secret' => env('DRISHTI_WEBHOOK_SECRET'),
    ],

    // socialmediadost.com — AI content production studio.
    // The CRM provisions clients here when a deal is won.
    'smdost' => [
        'base_url' => env('SMDOST_API_URL', 'https://socialmediadost.com'),
        'service_key' => env('SMDOST_SERVICE_KEY'),
    ],

    // eSSL biometric device ADMS push. BIOMETRIC_DEVICE_SERIAL must match the
    // serial number printed on the device (shown in its Command Center screen).
    'biometric' => [
        'device_serial' => env('BIOMETRIC_DEVICE_SERIAL'),
    ],

    // Shared secret for cross-portal SSO tokens (CRM → Drishti / SMDost).
    // Used to sign short-lived HS256 JWTs that let a portal contact log into
    // Drishti or SMDost without a separate password.
    'portal_sso' => [
        'secret' => env('PORTAL_SSO_SECRET'),
    ],

    /*
     | Anthropic (Claude) API — Phase 5 AI features. All AI is gated by the
     | `enabled` flag (AI_ENABLED). The key is never hardcoded; request/response
     | bodies are never logged (they contain customer data). Model defaults to
     | the spec-mandated claude-sonnet-4-20250514 and is overridable per .env.
     */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
        'enabled' => env('AI_ENABLED', false),
    ],

];
