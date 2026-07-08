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
- Laravel 12, PHP 8.2 (see Decisions log — originally specced as Laravel 11)
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
- Service (taxonomy) → NEDS service lines: SEO, GMB, Website Design &
  Development, Social Media, Performance Marketing, Software Development,
  AI Automation, AMC Service (seed these; admin can add more). Deals,
  projects, quotation line items, and tickets reference a service_id so
  every report can be sliced service-wise.
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
- Use model `claude-haiku-4-5-20251001` unless told otherwise. Max ~1000 output
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

## Decisions log
Record every "we chose X because Y" here — this is the project's memory.

- **2026-06-10 — Laravel 12 instead of Laravel 11 (Milestone 0).** The spec
  called for Laravel 11, but every Laravel 11 release is blocked by an
  unpatched High-severity advisory (GHSA-5vg9-5847-vvmq, CVSS 8.9 — CRLF
  injection in email validation; no auth/debug needed). It was fixed only in
  12.60.0+/13.10.0+, and the 11.x line will not be patched. Since this CRM
  emails user/contact-supplied addresses throughout (portal invites, ticket
  notifications, follow-up/payment reminders), staying on 11 would knowingly
  ship that vuln. Chose Laravel **12.62.0** — same stack (Breeze Blade +
  Livewire 3 + Tailwind + Pest), PHP 8.2+, fully Hostinger-compatible.
- **2026-06-10 — Livewire pinned to ^3.0 (v3.8.1).** Composer's latest is
  Livewire 4; spec mandates Livewire 3 and nothing forces an upgrade (v3 runs
  on Laravel 12), so pinned to ^3.0.
- **2026-06-10 — Per-user menu overrides (`menu_item_user`) are cosmetic
  only.** They show/hide sidebar items per user; actual route access is always
  role-based and enforced by middleware/Policies. Confirmed with owner.
- **Local dev MySQL:** MySQL 8.4 (service `MySQL84`), DB `neds_crm`. The
  `mysql` CLI is not on PATH on the dev machine — use the full path under
  `C:\Program Files\MySQL\MySQL Server 8.4\bin\` if needed; Laravel itself uses
  the `pdo_mysql` driver and is unaffected.
- **2026-07-06 — Service taxonomy: "Google Ads" renamed to "Performance
  Marketing"; added "AMC Service" as its own 8th service line.** Kiran shared
  a full service-task checklist (SEO, GMB, Website Dev, Social Media,
  Performance Marketing, Software Dev, AI Automation, AMC Service — each with
  a "new client" one-time setup list and an "existing client" recurring
  monthly list). Renamed via a data migration that updates the existing
  `services` row in place (same id, same `service_id` on every deal/lead/
  project/ticket/quotation/recurring-invoice — nothing re-links), since
  "Performance Marketing" better matches the broader paid-media scope
  (audience research, conversion tracking, creative testing) than "Google
  Ads" specifically. AMC Service is new — previously AMC/maintenance-only
  work had no service of its own and was folded into Website Development.
  **Discovered mid-deploy that production's "Website Development" service
  had separately been renamed by the team to "Website Design &
  Development"** (real drift from this doc, unrelated to this change) —
  confirmed with owner to keep that name and update the templates/tests to
  match it, rather than let the seeder create a duplicate service or
  silently rename the team's live label back. Caught by checking production
  service data directly before running the seeder, not assumed.
  **Deliberately did not convert every line item in Kiran's doc into an
  individual auto-created task** — that would have multiplied routine-task
  volume ~5-10x per project, re-introducing the exact "task flood" problem
  the Client Radar / Emptask filter / team-workload-summary work (same week)
  was built to fix. Instead each doc section (e.g. "On-Page SEO") became one
  consolidated recurring task with the checklist in its description —
  confirmed with owner via AskUserQuestion. Also added a genuinely new
  feature: a one-time onboarding checklist auto-created via
  `App\Jobs\CreateOnboardingTasks` (hooked off `Project::booted()`'s
  `created` event) when a project starts, using the same
  consolidated-per-section shape.
- **2026-07-08 — No new "Project Manager" role added; relabeled the existing
  `Project.owner_id` field instead.** The team asked for a "Project Manager
  role... since there might be a different person for different projects, or
  one person with many projects." Investigated before building: `Project`
  already has an `owner_id` (any user, any global role, can be set per
  project) plus a per-project `assignees` pivot with a `role` of `lead`/
  `member`; `ProjectPolicy` already scopes update rights to the owner (or
  admin/manager); the Projects index already auto-scopes non-admin/manager
  users to only their own owned/assigned projects, and admin/manager get a
  `?mine=1` toggle for the same view. This already fully satisfies "different
  manager per project, one person many projects" with zero new code — adding
  a literal `UserRole::ProjectManager` enum case would have meant deciding a
  whole new permission profile and touching every policy that branches on
  role, for no behavior the app doesn't already have. Confirmed the gap was
  purely internal terminology via AskUserQuestion, then just relabeled
  "Owner" → "Project Manager" in the internal project form/show page
  (`owner_id` column name unchanged). The client portal already independently
  shows this same person as "Account Manager" — left as-is, since that's the
  right word for that audience and was already distinct from the internal
  label.
