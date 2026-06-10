# CLAUDE.md — Niranjan Enterprises Digital Solutions CRM

## What this project is
A fully custom CRM for Niranjan Enterprises Digital Solutions (NEDS), a digital
solutions company in Maharashtra, India. 10 internal users across sales, support,
accounts, and management. Customers access a separate read-only portal.

## Deployment target — IMPORTANT CONSTRAINTS
This app deploys to **Hostinger shared hosting (Business plan)**. That means:
- PHP 8.2+ and MySQL only. NO long-running Node processes, NO Docker, NO Redis.
- Queue driver: `database` (workers triggered via cron, not supervisor/daemon).
- Cache driver: `database` or `file`. Session driver: `database`.
- Scheduler: a single cron entry running `php artisan schedule:run` every minute.
- File storage: local disk (`storage/app`), symlinked to `public/storage`.
- Document root will be `public_html` → build so that `/public` contents can be
  served from `public_html` with the app code one level above it.
- No websockets. Use polling for "real-time-ish" features.

## Tech stack (do not deviate without asking)
- Laravel 11, PHP 8.2
- MySQL 8 (use only features available on shared hosting MySQL)
- Frontend: Blade + Livewire 3 + Alpine.js + Tailwind CSS (no separate SPA,
  no Inertia, no React — keeps the Hostinger deploy simple)
- PDF generation: barryvdh/laravel-dompdf
- Auth: Laravel Breeze (Blade stack), extended with roles
- Charts: Chart.js via CDN
- AI: Anthropic API (Claude), called via Laravel HTTP client. API key in .env
  as ANTHROPIC_API_KEY. Never hardcode keys. Never log request/response bodies
  containing customer data.

## Architecture rules
- Standard Laravel structure. Fat models are fine for this scale; extract to
  Action classes (app/Actions) only when a method exceeds ~50 lines.
- All money values: store as integer paise (INT), display as rupees. Never floats.
- All user-facing dates: Asia/Kolkata timezone. Store UTC in DB.
- Every table gets `created_at`, `updated_at`. Soft deletes on Customer, Lead,
  Deal, Invoice, Ticket.
- Authorization: Laravel Policies for every model. Roles: admin, manager,
  sales, support, accounts. A `role` column on users is fine (no package needed).
- Menu visibility ("Menu Controller"): sidebar items are defined in a
  `menu_items` table (key, label, icon, route, sort_order) with a
  `menu_item_role` pivot for role defaults and a `menu_item_user` pivot for
  per-user overrides (granted/revoked). The sidebar renders from this, cached
  per user. IMPORTANT: hiding a menu is cosmetic only — route access is still
  enforced by Policies/middleware. Never rely on menu visibility for security.
- The Tasks module is labeled "Emptask" in the sidebar (team's existing term).
- Activity logging: log create/update/delete on core models to an `activities`
  table (who, what, when, changed fields as JSON).
- Validation: FormRequest classes, never inline validation in controllers.
- All queries through Eloquent / query builder. NEVER raw SQL with interpolation.

## Domain model (core entities)
- User (internal staff, role-based)
- Customer (company) → has many Contacts (people)
- Lead → converts to Customer + Deal
- Deal (pipeline stages: new, contacted, proposal, negotiation, won, lost)
- Quotation → belongs to Deal, has line items, GST per item, generates PDF
- Invoice → GST-compliant (CGST/SGST for Maharashtra intra-state, IGST for
  inter-state), invoice number format: NEDS/{FY}/{sequence} e.g. NEDS/2026-27/0042.
  Financial year runs April–March.
- Payment → belongs to Invoice (partial payments allowed)
- Project → created from won Deal, has Tasks
- Task → assignee, due date, status, belongs to Project or standalone
- Ticket → belongs to Customer, priority, status, assignee, SLA due time
- Note / Interaction → polymorphic, attaches to Lead/Customer/Deal/Ticket
- Attachment → polymorphic file uploads
- Service (taxonomy) → NEDS service lines: SEO, GMB, Website Development,
  Social Media, Google Ads, Software Development, AI Automation (seed these;
  admin can add more). Deals, projects, quotation line items, and tickets
  reference a service_id so every report can be sliced service-wise.
- Attendance → per user per day: check_in_at, check_out_at, status
  (present/half_day/leave/absent), notes. Self check-in via dashboard button;
  admin/manager can correct entries (corrections logged to activities).
- CallLog → user, callable (polymorphic: customer/lead), direction
  (incoming/outgoing), duration_minutes, outcome (connected/no_answer/busy/
  follow_up_needed), notes, called_at. Feeds employee performance reports.
- DailyReport → per user per day: auto-compiled metrics (tasks completed,
  calls made, leads touched, attendance status) + free-text "what I did
  today" submitted by the employee.

## Business context
NEDS is a digital solutions company with two kinds of revenue:
1. Recurring monthly retainers — SEO, GMB, social media, ads management,
   and AMC/support contracts. Recurring invoices are core.
2. Project-based work — website development, software development, and AI
   automation builds. These are typically billed in milestones (e.g. advance
   on signing, balance on delivery), so quotations/invoices must support
   partial/milestone billing against one deal.
UI terminology: use "Clients" everywhere this spec says "Customers" — that
is the word the team uses.

## GST rules (India)
- Company state: Maharashtra (state code 27).
- If customer GSTIN state code == 27 → split tax as CGST + SGST (half each).
- Else → IGST at full rate. Default rate 18% for services, editable per line item.
- Validate GSTIN format: 15 chars, regex `^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$`.
- Invoices must show: GSTIN of both parties, HSN/SAC code per line, tax breakup,
  amount in words.

## AI features (Phase 5 — build behind a feature flag `AI_ENABLED`)
- Lead scoring: on lead create/update, queue a job that sends lead details to
  Claude and stores a 0–100 score + one-line reason.
- Draft replies: button on Lead/Ticket → Claude drafts a reply using the
  interaction history; user edits before sending. Never auto-send.
- Thread summary: button on Customer/Ticket timeline → Claude summarizes.
- Use model `claude-sonnet-4-20250514` unless told otherwise. Max ~1000 output
  tokens per call. Wrap all AI calls in try/catch; AI failure must never break
  a core workflow.

## Coding conventions
- PSR-12. Descriptive names. No abbreviations in DB columns.
- Tests: Pest. Write feature tests for every controller action that mutates
  data. Critical paths needing tests: GST calculation, invoice numbering,
  deal stage transitions, role permissions.
- Commits: conventional commits (feat:, fix:, chore:).
- Migrations are append-only once merged — never edit an existing migration.

## Commands
- `composer dev` → run local server + vite (define in composer.json scripts)
- `php artisan test` → run before claiming any task is complete
- `npm run build` → production assets (committed or built before FTP deploy)

## Deployment notes
- Target: Hostinger Git deployment or FTP upload of built app.
- .env is created manually on the server, never committed.
- After deploy: `php artisan migrate --force`, `php artisan config:cache`,
  `php artisan route:cache`, `php artisan view:cache`.

## Do NOT
- Do not add packages without checking shared-hosting compatibility.
- Do not use Redis, Horizon, Reverb, Pusher, or node servers.
- Do not store customer PII in logs.
- Do not bypass Policies "temporarily".
- Do not delete or modify data in seeders that may run in production.
