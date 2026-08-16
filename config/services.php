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
    // support_number: digits-only business number (matches wadesk.in's Contact.phone
    // convention, e.g. 918007733737) for the post-sale support line. An inbound
    // webhook payload whose whatsapp_number differs from this (or that has no
    // whatsapp_number at all — an older wadesk.in build, or a not-yet-configured
    // line) is treated as the support line for backward compatibility; every
    // OTHER known line is always routed to the Lead flow, never a Ticket.
    // handoff_template_name: the wadesk.in Template.name of the Meta-approved
    // "welcome to support" template sent when a Deal is Won (see Deal::booted()).
    // Null until the template is actually submitted and approved in Meta
    // Business Manager AND added on wadesk.in's /templates page — the job
    // no-ops (logs, never throws) while unset.
    // marketing_number: digits-only business number (e.g. 919112095202) for
    // the pre-sale Marketing line — used by SyncLeadToWadeskJob to stage a
    // newly created/reassigned Lead in wadesk.in without sending a message,
    // and by SendVisibilityAuditPaymentConfirmationJob to send the paid
    // template below.
    // visibility_audit_payment_template_name: the wadesk.in Template.name of
    // a Meta-approved "Utility" template confirming a Visibility Audit offer
    // payment (see App\Jobs\SendVisibilityAuditPaymentConfirmationJob) —
    // same null-until-approved contract as handoff_template_name above.
    // Suggested body (submit as-is or adapt): "Hi {{1}}, we've received your
    // payment for the {{2}}. Our team will begin work shortly and reach out
    // here on WhatsApp with any questions. Thank you for choosing Niranjan
    // Enterprises Digital Solutions!" — {{1}} = payer name, {{2}} = tier
    // label + amount, e.g. "GBP Audit (₹120.00)".
    'wadesk' => [
        'base_url' => env('WADESK_API_URL', 'https://wadesk.in'),
        'service_key' => env('WADESK_SERVICE_KEY'),
        'support_number' => env('WADESK_SUPPORT_NUMBER'),
        'marketing_number' => env('WADESK_MARKETING_NUMBER'),
        'handoff_template_name' => env('WADESK_HANDOFF_TEMPLATE_NAME'),
        'visibility_audit_payment_template_name' => env('WADESK_VISIBILITY_AUDIT_TEMPLATE_NAME'),

        // Recovery nudges (SendVisibilityAuditRecoveryNudges, scheduled) —
        // one template per funnel stage. Body has {{1}}=name; the recovery
        // link is a Dynamic-URL CTA button (its own {{1}}=lead id, sent as
        // buttonUrlParam), not a second body variable. Ships inert until
        // each is set (template must be Meta-approved first).
        'visibility_audit_recovery_landing_template_name' => env('WADESK_VISIBILITY_AUDIT_RECOVERY_LANDING_TEMPLATE_NAME'),
        'visibility_audit_recovery_checkout_template_name' => env('WADESK_VISIBILITY_AUDIT_RECOVERY_CHECKOUT_TEMPLATE_NAME'),
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

    /*
     | Google Cloud Speech-to-Text — used only to transcribe Call Log voice
     | notes (Hindi/Marathi/English) before Claude translates/cleans the
     | result into English. Gated by the same AI_ENABLED flag as Anthropic;
     | this is a plain API-key REST call, no SDK, so it stays Hostinger-safe.
     */
    'google_speech' => [
        'api_key' => env('GOOGLE_SPEECH_API_KEY'),
    ],

    /*
     | Google Meet Notes (Phase 1) — per-user OAuth (confirmed with the owner,
     | not domain-wide delegation) so the CRM can read a staff member's own
     | Calendar events and Drive-hosted Meet recordings/transcripts. Plain
     | OAuth2 + REST via Laravel's HTTP client, no google/apiclient SDK — same
     | Hostinger-safe precedent as `google_speech` above. Independent of
     | AI_ENABLED since Phase 1 has no AI step (Phase 2 will also require it).
     */
    'google_meet' => [
        'enabled' => env('GOOGLE_MEET_ENABLED', false),
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => rtrim((string) env('APP_URL'), '/').'/settings/google/callback',
    ],

    /*
     | Razorpay — online invoice payment (Client Portal "Pay Now"). Same
     | merchant account/key pair already live on niranjanenterprises.com — a
     | Razorpay key pair is scoped to the account, not a domain, so it's
     | shared. webhook_secret is DIFFERENT from the website's: it's the
     | secret for a second webhook subscription added in the Razorpay
     | Dashboard (Settings → Webhooks) pointing at this CRM's own
     | /api/webhooks/razorpay endpoint, so the two integrations' webhook
     | verification never collides. The "Pay Now" button is hidden entirely
     | when key_id is empty (e.g. local dev without credentials).
     */
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

        // Visibility Audit offer landing page (/offers/visibility-audit).
        // Each of these is a Razorpay Payment Page URL (Dashboard → Payment
        // Pages — a separate, simpler product from the Orders API above),
        // one per offer tier. The page hides a tier's CTA entirely rather
        // than link to a blank/'#' URL when its value is unset, so this is
        // safe to leave blank until the Payment Pages actually exist.
        'payment_pages' => [
            'gbp_audit' => env('RAZORPAY_PAYMENT_PAGE_GBP_AUDIT'),
            'website_audit' => env('RAZORPAY_PAYMENT_PAGE_WEBSITE_AUDIT'),
            'both_audit' => env('RAZORPAY_PAYMENT_PAGE_BOTH_AUDIT'),
        ],

        // A THIRD, separate webhook subscription/secret (Dashboard →
        // Settings → Webhooks → new subscription, event: payment.captured
        // only) pointed at /api/webhooks/razorpay/visibility-audit — kept
        // distinct from webhook_secret above so a Payment Page purchase can
        // never be confused with (or accidentally authenticate as) an
        // invoice payment. Same one-secret-per-integration pattern as every
        // other webhook this app receives.
        'visibility_audit_webhook_secret' => env('RAZORPAY_VISIBILITY_AUDIT_WEBHOOK_SECRET'),
    ],

    // Telegram Bot API — posts a plain HTTP message to one shared group chat
    // whenever a new Lead is created (see App\Jobs\SendTelegramLeadAlertJob).
    // bot_token comes from @BotFather; chat_id is the target group's own
    // numeric id (e.g. -1001234567890 — get it by adding the bot to the
    // group, sending any message, then GET https://api.telegram.org/bot
    // <token>/getUpdates and reading the "chat":{"id":...} field). No
    // webhook/polling needed for outbound sends. No-ops until both values
    // are set — ships inert, starts working the moment the owner creates
    // the bot and sets both env vars.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

];
