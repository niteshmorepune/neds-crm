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
    // BIOMETRIC_BRIDGE_TOKEN is a separate shared secret for the office-LAN
    // bridge script polling/reporting on manual "Sync now" requests — not the
    // device itself, which only ever sends BIOMETRIC_DEVICE_SERIAL.
    'biometric' => [
        'device_serial' => env('BIOMETRIC_DEVICE_SERIAL'),
        'bridge_token' => env('BIOMETRIC_BRIDGE_TOKEN'),
    ],

    // Shared secret for cross-portal SSO tokens (CRM → Drishti / SMDost).
    // Used to sign short-lived HS256 JWTs that let a portal contact log into
    // Drishti or SMDost without a separate password.
    'portal_sso' => [
        'secret' => env('PORTAL_SSO_SECRET'),
    ],

    // Meta (Facebook/Instagram) Lead Ads webhook. app_secret verifies the
    // X-Hub-Signature-256 header on inbound events; webhook_verify_token is
    // checked against Meta's GET handshake (hub.verify_token) when the
    // webhook subscription is registered in the Meta App Dashboard;
    // page_access_token authorizes the follow-up Graph API call that fetches
    // the actual lead field data (Meta's webhook payload only contains a
    // leadgen_id, never the submitted fields themselves).
    'meta' => [
        'app_secret' => env('META_APP_SECRET'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v19.0'),
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
        // Lead score at/above this triggers an immediate HotLeadNotification
        // to the owner, instead of waiting for the 9am morning digest.
        'hot_lead_threshold' => env('AI_HOT_LEAD_THRESHOLD', 70),
        // Rough USD cost per MILLION tokens, for the AI Usage report's cost
        // estimate only (app\Services\AiUsageMetrics) — never used for any
        // real financial/GST figure. An unrecognised model falls back to
        // 'default'. Update these if Anthropic's published pricing changes.
        'pricing' => [
            'claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],
            'default' => ['input' => 1.00, 'output' => 5.00],
        ],
        // Rough USD->INR rate for converting the estimate above to ₹.
        'usd_to_inr' => (float) env('AI_USD_TO_INR', 87),
        // Max questions a single portal contact can ask the client-facing
        // portal assistant per rolling 24h — this is the only AI feature a
        // client (not staff) can trigger themselves, so it's rate-limited
        // where nothing else in the app has needed to be.
        'portal_assistant_daily_limit' => (int) env('AI_PORTAL_ASSISTANT_DAILY_LIMIT', 15),
    ],

];
