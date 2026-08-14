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
- **2026-07-08 — Multi-role support: primary role (`users.role`, unchanged)
  + additional roles (`role_user` pivot), not a full role-model rewrite.**
  The team asked whether a person can hold two roles at once (e.g. both
  Sales and Support). A full codebase scan first confirmed the blast radius:
  ~14 Policies and ~15 Controllers already call `hasRole(...$roles)` as a
  variadic OR-check, so they needed zero changes once `hasRole()`/`isAdmin()`
  were taught to also look at a new `role_user` pivot (`App\Models\
  UserRoleAssignment`) alongside the existing scalar `users.role` column.
  Kept `users.role` as the single **primary** role driving everything that
  must pick exactly one value:
  - **Menu Controller sidebar** (`App\Services\MenuResolver`) — left
    untouched; its cache keys and role-lookup query are structurally keyed
    on the single scalar role. An additional role does not auto-expand the
    sidebar — grant the extra items via the existing per-user
    `menu_item_user` override instead (already built for this).
  - **Dashboard panel** (`App\Http\Controllers\DashboardController`) — this
    one required an actual code change, not just "leave it alone": its
    `match(true)` panel-priority ladder was built on `hasRole()`, so
    rewriting `hasRole()` would have let a secondary role silently outrank
    the primary role and switch someone's panel (e.g. a Support user given
    Sales as a secondary role would start seeing the Sales panel). Rewrote
    it to branch on `$user->role` directly so the panel always follows the
    primary role only, consistent with the sidebar decision.
  - **2FA enforcement** (`User::requiresTwoFactor()`, unchanged code) — DOES
    now also trigger for a secondary Admin/Manager role, since it already
    called `hasRole()`. Considered desirable, not a bug.
  Additional roles DO expand: direct Policy/permission checks (automatic,
  via `hasRole()`); role-targeted broadcast notifications and eligibility
  (Deal Won, SLA-breach escalation, leave-request approval + its
  notification, recurring-invoice due warnings, monthly report reminder,
  SMDost brief-approved, payment-recorded — all converted from raw
  `where('role', ...)`/`whereIn('role', [...])` to a new
  `User::scopeWithAnyRole()`, which unions the primary column with the
  pivot); and Client/Lead owner-picker dropdowns (manual admin selection, so
  it doesn't conflict with keeping auto-assignment primary-role-only).
  **Deliberately left as single-assignee/primary-role-only** (picking ONE
  person to own/route something is a different concern from "who's
  notified" or "who's a valid dropdown candidate," confirmed via
  AskUserQuestion): `LeadObserver::autoAssign()` (least-loaded Sales rep for
  a new lead — only its *fallback broadcast* to all Sales reps when nothing
  is auto-assigned was converted), `CreateOnboardingTasks` /
  `DispatchScheduledTasks` (routing an auto-generated task to a project's
  Support-role assignee), `DraftFestivalGreetingContent` (single fallback
  admin creator/recipient), and `AttendanceController`'s
  `where('role', '!=', Admin)` negation (a hypothetical secondary-Admin
  would still appear in a manager's attendance list — accepted, since Admin
  as a secondary role is not a realistic scenario here).
  **One pre-existing behavior surfaced, not introduced, by this change:**
  `Customer::scopeVisibleTo` (converted from a raw `$user->role ===
  UserRole::Sales` check to `hasRole()`, to keep mirroring
  `CustomerPolicy::view`, which already used `hasRole()`) — and
  `CustomerPolicy::view` itself — both check "has the Sales role at all"
  *before* any broader role's access, so a user whose primary role already
  grants full client visibility (e.g. Support) will be narrowed to
  owned-or-unassigned-only once Sales is added as an additional role. This
  is how the Policy was already written for a hypothetical multi-role user;
  multi-role support just made it reachable. Not changed, since altering
  Policy priority order was out of scope for this feature — flagged here in
  case it ever surprises someone.
- **2026-07-09 — Menu Controller: additional roles now auto-expand the
  sidebar.** Supersedes the "left untouched" call in the 2026-07-08 entry
  above. `MenuResolver::accessibleKeys()` now unions the primary role with
  the `role_user` pivot (via `$user->allRoles()`) instead of checking
  `$user->role->value` alone, and its cache key moved from per-role
  (`access:role:{role}`) to per-user (`access:user:{id}`) since access is no
  longer determined by primary role alone. Since `computeVisibleItems()`
  already derives sidebar visibility from `accessibleKeys()`, this same
  change also makes the sidebar follow additional roles — no separate
  visibility change was needed. `UserController::update()` now flushes the
  menu cache when additional roles change, not just when the primary role
  changes. The dashboard panel (`DashboardController`) is untouched and
  still follows the primary role only — that decision (avoid a secondary
  role silently outranking the primary role's panel) still stands and is
  unrelated to sidebar/route access. Owner confirmed this had become real
  friction (manual per-user Menu Controller overrides for every additional
  role grant), which is exactly the trigger condition the original
  deferred-item note called for.
- **2026-07-19 — Added `docs/developer-guide.md`; replaced the stock
  Laravel `README.md`/`composer.json` identity.** The repo had never had
  real developer-facing documentation — `README.md` was still the
  untouched Laravel skeleton boilerplate, and `composer.json` still said
  `"name": "laravel/laravel"` and `"license": "MIT"`, six weeks into a
  proprietary internal build. Added `docs/developer-guide.md` (local
  setup, testing, architecture, the Policies-vs-Menu-Controller
  distinction, scheduled jobs, the AI integration pattern, GST/money/date
  conventions, why Hostinger shapes every dependency choice) and rewrote
  `README.md` as a real front door pointing to it, this file, and the rest
  of `docs/`. Renamed the composer package to
  `niranjanenterprises/neds-crm`, license `proprietary`. **Caught two real
  documentation bugs while writing it, not assumed**: `MenuResolver`'s
  cache flush is already wired into the normal Users/Menu Controller UI
  flows (`MenuResolver::flush()`), not something a developer needs to
  remember to call — corrected a first draft that implied otherwise; and
  the scheduler lives in `routes/console.php`, not `bootstrap/app.php` —
  confirmed by reading the actual file rather than guessing from Laravel
  version conventions. Also reproduced and documented the `php artisan
  test` vs `vendor/bin/pest` memory-limit gotcha with the exact working
  command (`php -d memory_limit=512M vendor/bin/pest`) rather than a vague
  pointer, after hitting the fatal firsthand.
- **2026-07-19 — Bumped `guzzlehttp/guzzle` (7.11.1→7.15.1) and
  `guzzlehttp/psr7` (2.11.0→2.13.0), patching 3 medium-severity CVEs.**
  Surfaced by `composer audit` while re-locking `composer.json` for the
  identity fix above (unrelated to it) — dot-only cookie domains matching
  all hosts and a silent HTTPS-proxy-downgrade issue in guzzle
  (CVE-2026-55767, CVE-2026-55568), plus CRLF injection in psr7's
  HTTP start-line serialization (CVE-2026-55766). Same category of issue
  as the Laravel 11→12 CVE that drove the very first entry in this log,
  though lower severity. Both are transitive dependencies (pulled in by
  Laravel's own HTTP client, which `AnthropicClient` and the outbound
  Drishti/SMDost/wadesk webhook calls all use) — not pinned in
  `composer.json`, so only `composer.lock` changed. `composer audit`
  reports zero advisories after the bump; full test suite (1039 tests)
  re-verified passing.
- **2026-07-23 — Sales Incentive module: incentive basis = Deal.value at Won,
  not invoice/payment collected.** The owner asked for a tiered monthly
  incentive (6%/10%/12.5%/15%/20% marginal slabs on before-tax sales) plus a
  small team-pool bonus. The obvious alternative — computing "sales" from
  actual payments collected — was rejected after checking the codebase: it
  would need prorating GST off every partial/milestone payment and
  attributing it back through invoice→deal→owner, real complexity for a
  number that already exists. `Deal.value` at the moment a deal is marked
  Won is already pre-tax (GST is only added downstream at quotation/invoice
  line-item level) and is already the exact figure `SalesPipelineMetrics`
  uses for `won_this_month_value`, the rep leaderboard, and `SalesTarget`
  progress — so the incentive number and the target-progress number the
  reps already see can never quietly disagree. Confirmed with the owner via
  AskUserQuestion, along with three other decisions: **eligibility** is the
  Sales role only (primary or additional, via the existing
  `hasRole()`/`withAnyRole()` multi-role support — Admin/Manager who
  occasionally own a deal directly do not earn incentive on it); the
  **team bonus** is a fixed ₹10,000/month pool (admin-editable, new
  `incentive_settings` singleton table) split evenly across active Sales
  users, gated on the existing company-wide monthly `SalesTarget`
  (`user_id = null`) being met — reused as-is, no new target concept; and
  **finalization** is live all month (`App\Services\IncentiveCalculator`,
  nothing stored, recalculated on every `/incentives` view) with a
  `app:finalize-incentives` command snapshotting each rep's just-ended
  month into a locked `incentive_statements` row on the 1st (same
  `monthlyOn(1, ...)` pattern as `app:draft-monthly-wins-notes` /
  `app:create-monthly-briefs`), so payroll has a stable number even if a
  Deal is edited after month close. Slab math is marginal/bracket-style
  (like income tax), not a cliff on the whole amount — deliberately, to
  avoid a rep holding back a deal near a bracket boundary; verified with a
  dedicated test asserting ₹50,001 is NOT taxed as 10% of the whole amount.
  Shipped as its own "Incentives" sidebar item (`menu.access:incentives`)
  rather than folded into the already-dense Sales Dashboard, so a Sales
  rep sees only their own numbers and Admin/Manager see everyone's plus
  the pool-amount form; company-target editing itself stays on the Sales
  Dashboard (linked from this page) rather than duplicating that form.
- **2026-07-24 — Google Meet Notes (Phase 1): per-user OAuth, not
  domain-wide delegation; new `/settings/google/*` routes, not nested
  under `/profile`.** Team asked for a Call-Log-style Google Meet
  recording feature. Confirmed via AskUserQuestion: (1) each staff
  member connects their own Google account (standard OAuth
  authorization-code flow, plain REST via Laravel's HTTP client — no
  `google/apiclient` SDK, same Hostinger-safe precedent as
  `GoogleSpeechClient`) rather than the owner granting one service
  account domain-wide delegation to impersonate any user — lower blast
  radius, and whoever imports a meeting is almost always its own
  organizer anyway; (2) meetings attach to Customer + Lead only,
  mirroring `CallLog`'s `callable` polymorphic scope exactly, not the
  broader Note-style scope; (3) embedded only (an "Import Meet Notes"
  button inside the Calls tab / a new section on Lead show) — no new
  sidebar "Meetings" list page; (4) two phases — this PR is Phase 1
  (OAuth connect + Calendar-event picker + raw transcript/recording
  link, no AI), Phase 2 (later) adds a Claude-summarized version reusing
  the existing `AnthropicClient` + `ai_usages` logging.
  New `google_account_connections` (encrypted tokens) + `meetings`
  tables. OAuth redirect URI is registered in Google Cloud Console as
  `/settings/google/callback` — deliberately a new `/settings/*` area
  rather than `/profile/google/callback` (which would have matched
  where the "Connect Google Account" UI actually lives, on the Profile
  page) to avoid a round-trip back to Google Cloud Console to
  re-register the URI after already creating the OAuth client with the
  first one. New `GOOGLE_MEET_ENABLED` flag (independent of
  `AI_ENABLED` — Phase 1 has no AI step) gates both the connect UI and
  the import button.
  **Real Google-side surprise mid-build, not a code decision**: the
  owner's original Workspace signup (personal Gmail account, "Business
  Standard" active in Billing) had never had a domain/organization
  completed — Directory/Security/Devices and per-app settings were all
  missing from the Admin Console, and direct links 403'd. Root-caused
  before writing any workaround, rather than assumed a permissions bug.
  Fixed by connecting `niranjanenterprises.com` (the staff's real
  official email domain, kept deliberately separate from the CRM's own
  `niranjanenterprises.co.in` and from where real company email is
  actually hosted, cPanel) as the Workspace org domain — Workspace here
  is *only* for Calendar/Meet/Drive identities, MX records were never
  touched. Also hit and waited out a same-day propagation delay between
  finishing Workspace setup and Google Cloud recognizing the
  organization for project creation.
  **`GoogleMeetImportClient`'s attachment-matching logic (recording =
  `video/*`, transcript = a Google Doc attached to the same Calendar
  event) is UNVERIFIED against a real live recorded/transcribed
  meeting** as of this entry — no meeting has been held yet on the
  freshly-provisioned Workspace. If a real import comes back with an
  empty transcript despite Meet having genuinely finished processing
  one, check this matching logic first, live, against a real event's
  raw `attachments` payload before assuming a deeper bug.
- **2026-07-24 — Google Meet Notes Phase 2: Claude summary is persisted on
  the `meetings` row via a background job, not an ephemeral per-viewer
  button like Ticket/Customer summaries.** Append-only migration adds
  `ai_summary_status` / `ai_summary` / `ai_summarized_at` to `meetings`
  (mirrors `call_logs`' `voice_transcript_*` shape exactly, per Phase 1's
  own note that this would be a Phase 2 append-only migration). Chose the
  persisted-job pattern (`App\Jobs\SummarizeMeeting`, same shape as
  `TranscribeCallLogVoiceNote`) over the ephemeral on-demand pattern
  (`TicketReplies::summarize()`) because a meeting note is shared team
  context on a client/lead timeline — everyone who opens that page should
  see the same summary without re-clicking, not just whoever happened to
  click first. New `App\Enums\MeetingSummaryStatus` (Pending/Processing/
  Completed/Failed) and a small polling component
  (`App\Livewire\MeetingSummary`, mirrors `CallVoiceTranscript`) embedded
  per-meeting-row inside `MeetingImport`'s list, so it surfaces on both
  Customer and Lead pages with no extra wiring. The summary is
  auto-queued right after a successful import when a transcript came back
  and `GoogleMeet::summaryEnabled()` (new: `enabled() && Ai::enabled()`,
  same combined-gate idiom as `Ai::voiceTranscriptionEnabled()`) is true;
  a manual "Summarize with AI" / "Retry" trigger on the same component
  covers meetings imported without a transcript yet, or a failed attempt.
  Deliberately skipped: no thumbs-up/down feedback widget (unlike
  Ticket/Customer summaries) — matches `ScoreLead`'s precedent of no
  feedback UI for a persisted, non-ephemeral AI result, and would have
  needed a new `ai_summary_usage_id` column for no strong reason yet. The
  call still goes through `AnthropicClient::message()` and is logged to
  `ai_usages` under feature `summarize_meeting` (added to
  `AiUsageMetrics::label()`), so it appears in the AI Usage Report
  automatically like every other AI feature.
- **2026-07-24 — Staff Productivity Ranking: rank within primary role only,
  private to each employee, informational-only for now.** Owner asked
  whether AI could show who's most productive among staff, framed to help
  people improve rather than just judge them, plus a full overview for
  the owner. Confirmed via AskUserQuestion: each employee sees only their
  own rank (never a public leaderboard — Admin/Manager keep full
  oversight, matching the existing Employee Performance Report's own
  access model); scoring reuses `ReportMetrics::employeePerformance()`'s
  existing 6 metrics as-is (no new data collection); stays informational
  only for now, deliberately kept separate from the Sales Incentive
  module (which is Sales-only and tied to `Deal.value`); and it extends
  the existing Employee Performance Report rather than a new sidebar
  item. New `ReportMetrics::rankedEmployeePerformance()` groups by
  primary role (`$user->role`, matching `DashboardController`'s existing
  primary-role-only convention) — only Sales/Support/Accounts/Intern are
  ranked; Admin/Manager are evaluators, not participants, same
  distinction the Incentive module already makes. Within a role group of
  2+, each metric gets a 0–100 percentile rank (average-rank method, ties
  share the midpoint), combined into one composite score via a new
  `ROLE_WEIGHTS` table (plain PHP, adjustable later without a schema
  change), and the person's single lowest-percentile weighted metric is
  flagged `weakest_metric` — the concrete gap to close. Groups under 2
  people get a `ranking_note` ("Not enough peers... yet") instead of a
  fabricated rank. New `AiAssistant::suggestTeamProductivityGaps()` (one
  batched JSON call for the whole team, mirrors `suggestOnboardingTasks()`'s
  JSON-array pattern, matched back to rows by exact `user_id`) powers a
  new `App\Livewire\ProductivityGapSuggestions` component (sibling to
  `TeamPerformanceSummary`, same Admin/Manager-only guard) that now owns
  the entire ranked table (Score/Rank/Focus area columns, grouped by
  role) on `reports/employee-performance`. New
  `AiAssistant::suggestProductivityImprovement()` (single-person version)
  powers `App\Livewire\MyProductivity` — a private "your own rank + tip"
  widget embedded only on the Sales/Support/Accounts/Intern dashboard
  partials (never Admin/Manager), which only ever computes/shows the
  viewer's own row, the same guarantee every other per-role dashboard
  stat method already relies on (no new Policy needed).
- **2026-07-24 — Fixed a real production incident: Accounts dashboard's
  "Outstanding receivables" tile disagreed with the Receivables Report**
  (₹3,87,864 vs ₹2,31,440), reported by an Accounts user via screenshot.
  Root-caused via a read-only diagnostic script run directly against
  production (never guessed) — the two used separately-written queries
  that silently differed on two things: `DashboardMetrics::accountsStats()`
  didn't exclude invoices whose customer had been soft-deleted (the
  Receivables Report already did, via `whereHas('customer')`, a 2026-06-16
  fix to stop a null-pointer crash), and the two disagreed on whether
  Draft invoices count as "outstanding." The soft-deleted-customer
  exclusion was actually the wrong fix from the start — it made real
  unpaid money (₹1,56,424 across 11 invoices, from clients like Shridha
  Biotech, Prakash Electrical, and two apparent duplicate-cleanup records)
  silently invisible on the one report Accounts would actually use to
  chase it, instead of just showing it gracefully. Every other page
  (Deals/Projects/Quotations/Tickets/Invoices index+show) already solved
  this exact problem by showing a soft-deleted customer's records with a
  "Client removed" label instead of hiding them — confirmed with the owner
  via AskUserQuestion, then applied the same treatment to the Receivables
  Report. Extracted `CollectionsMetrics::outstandingInvoicesQuery()` as
  the single source of truth (Draft/Sent/PartiallyPaid/Overdue, no
  customer-existence filter) — both `InvoiceController::receivables()` and
  `DashboardMetrics::accountsStats()` now call it, so the two totals can
  never silently drift apart again. Separately, also made the Accounts
  dashboard's "Overdue invoices" count a link to the already-filterable
  `invoices.index?status=overdue` (a second, smaller gap reported in the
  same conversation) — the filter already existed, it just wasn't
  exposed from the dashboard tile.
- **2026-07-24 — Added a "Collected This Month" drill-down (`account/
  collected`, `InvoiceController::collectedThisMonth()`), the same gap as
  the Overdue invoices count above but for the dashboard's "Collected
  this month" figure.** No existing page listed individual `Payment` rows
  (payments are only ever visible inline on their own invoice's page) —
  built a small new report (date, client, invoice #, mode, recorded by,
  amount) rather than repurpose an unrelated page, gated by the same
  `menu.access:account` group and `InvoicePolicy::viewAny` the Receivables
  Report already uses. Same "Client removed" fallback for a payment whose
  invoice's customer has been soft-deleted, consistent with the
  Receivables Report fix above.
- **2026-07-24 — Real incident, not a bug: 11 real overdue invoices
  (₹1,56,424, from 6 already-soft-deleted clients) were manually deleted
  by an Accounts team member the same day the Dashboard/Receivables
  mismatch above was reported.** Confirmed via the `activities` log
  (`event=deleted`, real `user_id`, not a system/console action) rather
  than assumed — this was an intentional write-off the owner had already
  told that team member to do, unrelated to the code fix. Soft-deleted,
  not gone, if it ever needs reversing. Documented here only so a future
  session doesn't mistake the resulting clean total for evidence the
  code fix "solved" this specific batch — it didn't; the invoices were
  deleted before the fix could apply to them.
- **2026-07-24 — Recurring Services "Ended" → "Not Billed": relabel real
  records, don't hide them.** Team reported (via screenshot) that the
  Client Dashboard's Recurring Services/Invoices sections showed SEO/Social
  Media rows with no invoice ever generated for them. Investigated
  production data first: all 15 affected rows traced to one person, one
  24-minute window, `next_run_on` uniformly set far past `end_date` —
  deliberate historical record-keeping (a paused-before-first-bill
  template), not a data-entry mistake. `RecurringInvoice::isOrphaned()`
  already handled "invoice created then deleted" but had no branch for
  "deactivated, never billed at all" — that gap made `dashboardStatus()`
  fall through to `'ended'`, which reads as "billing completed
  successfully," the opposite of what happened. Confirmed with the owner
  via AskUserQuestion between hiding these rows vs. relabeling them
  honestly — chose **relabel**: added a `'not_billed'` status (new
  gray "Not Billed" badge in `_services_tab.blade.php`) returned whenever
  `dashboardStatus()` finds no invoice at all. Note: `'ended'` can now only
  ever be returned via the `!$revealPaymentStatus` early-return (a
  Support/no-invoice-access viewer) — once any invoice exists on the
  template, an Admin/Manager viewer always sees `'payment_received'` or
  `'payment_pending'`, never `'ended'`, regardless of paid/unpaid status.
- **2026-07-24 — Payment correction: date/mode/reference are editable
  in-place; amount/TDS still require delete-and-recreate.** Same
  conversation as above — team also reported a mistakenly-entered payment
  date could never be fixed without deleting and re-recording the whole
  payment. Checked `Invoice::refreshPaymentStatus()` first: it recomputes
  `amount_paid` purely from `payments()->sum('amount')`, so date/mode/
  reference genuinely don't feed any downstream calculation — safe to
  edit directly. Amount/`tds_amount` deliberately excluded from the new
  edit form (`PaymentUpdateRequest` only validates `paid_on`/`mode`/
  `reference`, the controller's `update()` call ignores any amount/
  tds_amount sent) because those drive `Invoice::balance()`/`status` and
  an already-sent `PaymentRecordedNotification` — correcting them still
  needs the existing delete-and-recreate path. Confirmed scope with the
  owner via AskUserQuestion ("Date, mode, and reference only"). New
  `PATCH /invoices/{invoice}/payments/{payment}` route, gated by the same
  `InvoicePolicy::recordPayment()` (accounts team) used to create a
  payment; inline Alpine.js `x-show` edit form per payment row on the
  invoice page, no new Livewire component (too small to justify one, same
  philosophy as other lightweight toggles in this app). `Payment` gained
  the `LogsActivity` trait (had none before) so a correction leaves an
  audit trail.
- **2026-07-24 — Notifications page no longer links to a deleted invoice.**
  Owner reported two invoice links from the Notifications page 404ing.
  Root-caused via the production activity log (not assumed): both
  invoices were real, legitimately deleted after their notification had
  already fired (one an internal SMDost-webhook draft invoice cleaned up
  an hour later, one a real recurring invoice deleted by Accounts the
  same day its "due soon" reminder went out) — so the notification itself
  was accurate at the time, but the link it stored became dead the moment
  the invoice was removed. Every invoice-linked notification type
  (`new_invoice`, `payment_recorded`, `payment_promise_broken`,
  `recurring_invoice_due_soon`, `smdost_brief_approved`) already stores an
  explicit `invoice_id` in its `data` JSON, so `NotificationController::
  index()` now batch-checks which of the current page's referenced
  invoices are soft-deleted (`Invoice::onlyTrashed()->whereIn('id', ...)`,
  one query for the whole page) and the view renders those as plain text
  with a "(invoice deleted)" note instead of a clickable dead link — same
  "relabel a real, removed record instead of a broken link" treatment as
  the Receivables Report's "Client removed" fix. Scoped to Invoice only
  (not `ContractRenewalDueSoon`'s `recurring_invoice_id`, a different
  model with no confirmed instance of this bug) — the bell-icon header
  badge is just an unread count linking to this same page, no separate
  preview list to fix.
- **2026-07-25 — Team Nudges: a new, reusable admin-managed reminder
  system, separate from the existing Notice Board.** Came out of a
  broader "what's the best plan to close the adoption gap" conversation
  (66 active clients but only 11 deals/3 tickets ever logged — see the
  2026-07-18 baseline). Owner asked for reminders that pop up on the
  Dashboard and get tracked, not just a one-off chat nudge. Checked
  `Announcement` (Notice Board) first — it's a broadcast, time-bound post
  with no per-user targeting, completion state, or auto-detection, so it
  doesn't cover this; built new `TeamNudge`/`TeamNudgeStatus` models
  alongside it rather than overloading it. Confirmed 3 design decisions
  with the owner via AskUserQuestion before building: (1) reusable, not
  hardcoded to today's 3 items — Admin/Manager can create a nudge anytime
  (title, target role or everyone, one-time or weekly recurrence, optional
  due date); (2) completion is **both** self-reported (Done/Snooze
  buttons on the recipient's own dashboard) **and** auto-detected for
  specific weekly checks (`App\Enums\NudgeAutoDetectType`, mapped 1:1 to a
  real Eloquent check in `App\Services\TeamNudgeDetector` — same bounded-
  registry discipline as `CrmQueryType`/`CrmQueryCatalog`, never a
  free-form condition); (3) an individual can snooze their own view, but
  the Admin/Manager team-wide overview always shows the real status
  (Pending/Snoozed/Done) — snoozing is per-viewer, never a way to hide
  something from oversight.
  **One targeting call made without asking, flagged in the plan instead**:
  a nudge's `target_role` is matched via `$user->hasRole()` (primary
  **and** additional roles), following the *sidebar's* precedent (2026-
  07-09: additional roles auto-expand menu access) rather than the
  *dashboard panel's* precedent (deliberately primary-role-only, so a
  secondary role never outranks which panel someone lands on) — a nudge
  is an access question ("does this apply to you"), not a "which single
  panel" question, so the sidebar rule is the closer match.
  A scheduled command pair drives the weekly lifecycle:
  `app:rollover-team-nudges` (Monday 06:00 IST) creates a fresh `pending`
  row per targeted active user without touching prior weeks' rows, so
  completion history accumulates instead of being overwritten;
  `app:run-team-nudge-auto-detect` (daily 06:15 IST) clears a pending
  auto-detect row the same day real activity happens, rather than making
  someone wait for the next Monday. `App\Livewire\MyTeamNudges` (embedded
  on all 5 dashboard partials, including Admin's — Admin is itself a
  valid nudge target for the "record training videos" item) lazily
  materializes its own status row via `firstOrCreate` if the scheduled
  job hasn't reached it yet (e.g. a brand-new nudge, or someone hired
  mid-week), sharing one `TeamNudge::currentPeriodStart()` helper with
  the console commands so a lazily-created row always lines up with a
  scheduler-created one. `App\Livewire\TeamNudgeOverview` deliberately
  derives the targeted-user list directly (not from existing status rows)
  so someone who hasn't opened their dashboard yet still shows as
  "Pending" rather than silently missing from the admin's view.
  Seeded 3 nudges via `TeamNudgeSeeder` (idempotent, `updateOrCreate` by
  title, added to `DatabaseSeeder`'s chain since it's real content the
  owner wants live, not demo data): record training videos (Admin,
  one-time, manual), log every active client as a Deal (Sales, one-time,
  manual — a retroactive backfill, not a recurring habit, so no
  auto-detect), route every issue through Tickets (Support, weekly,
  auto-detects on a real `Ticket.created_by` within the period). New
  sidebar item `team-nudges` (Admin/Manager, mirrors `announcements`'
  gating exactly). PR #101, branch `milestone-team-nudges` — full suite
  (1267 tests) green, Pint clean, migrated/seeded against local MySQL and
  the two console commands smoke-tested against real dev data before
  opening the PR. Manual browser click-through (Done/Snooze buttons,
  admin create/edit form) not done this session — flagged in the PR as
  the one gap before merge.
- **2026-07-29 — Portal invitation "Set My Password" 404: root-caused to
  NOT be a code bug, but the generic 404 it produced was a real gap —
  fixed with a friendly "link no longer valid" page.** Owner reported a
  client portal invite link 404ing. Investigated read-only against
  production (routes, URL generation, DB storage, `activities` log all
  checked out individually) before the owner supplied the actual emailed
  link plus a screenshot, which let hashing the literal token and
  matching it against production data pin the exact cause: an admin
  (Prathamesh Khobare) edited the same test contact's email, revoked
  portal access, re-invited it (sending the real email received), then
  66 seconds later edited it again and revoked access a second time —
  silently nulling the `invitation_token` the just-sent email pointed
  at. `inviteToPortal()`/`revokePortalAccess()` are working exactly as
  designed; a real client hitting a superseded or already-used link
  would get the identical bare 404 with zero explanation, indistinguishable
  from an actual bug. Fixed by having `SetPasswordController::show()`/
  `showReset()`/`store()` render a new branded `portal.auth.invalid-link`
  view (with a "Request a new link" button to the existing Forgot
  Password flow) instead of `firstOrFail()` throwing — `contactForToken()`
  now returns `?Contact` via `first()`. Added a troubleshooting.md entry
  explaining the (expected) causes for staff. Direct push to master
  (`78f86f2`, no PR — small, isolated bug fix), deployed same session.
- **2026-07-29 — Help Guide reorganized to match the actual CRM sidebar
  flow, not build history.** Owner reported the Help Guide should follow
  the real app flow in logical order. Used `MenuItemsSeeder`'s live
  sidebar array (the 2026-07-25 workflow-stage reorder) as the canonical
  order. `admin.md` was the worst offender — it listed admin-only Users
  and Menu Controller **first**, even though the real sidebar
  deliberately puts admin config **last** (see the Menu-Controller/
  sidebar entries above) — fully renumbered to mirror the sidebar, with
  every internal "Section N" cross-reference (and the external anchor
  from `integrations.md`, plus `meta-ads-playbook.md`'s prose refs)
  updated to match. Also reordered `manager.md`, `sales.md` (Incentives/
  Quotations were swapped vs. sidebar order; merged a stray duplicate
  "Quotations — sending to clients" section back into the main Quotations
  flow), `support.md` (Calls belongs between Tickets and Projects), and
  `intern.md`/`telecaller.md` (Attendance — a day-one habit — was listed
  last, after every role-specific module). `accounts.md` and
  `getting-started.md` were already correctly ordered. Fixed drift found
  along the way: README's guide table was missing the Telecaller row
  entirely and claimed "7 automated workflows" (integrations.md documents
  10). Direct push to master (`4f78eb6`, no PR — docs-only), deployed
  same session; `docs/user-guides/*.md` is read live from disk by
  `HelpController` (no content cache) and the PDF handouts aren't served
  by any route (confirmed via a routes grep, not assumed), so a plain
  `git pull` was the whole deploy.
- **2026-07-29 — Create Meeting scheduled a Google Meet call 5.5 hours
  off (12:00 PM became 5:30 PM) — real timezone bug, fixed.** Owner
  reported this with a screenshot of a real, already-sent "NEDS <> ADTA
  Group" Calendar event (real invites to the client and 3 staff) at the
  wrong time. Root cause: `app.timezone` is `UTC` (this file's own
  "store UTC, display Asia/Kolkata" convention), and
  `MeetingImport::createMeeting()` used bare
  `Carbon::parse($this->scheduleAt)` on the `datetime-local` input
  string — resolves against `app.timezone`, not `app.display_timezone`,
  so "12:00" was read as 12:00 UTC = 5:30 PM IST, exactly the reported
  offset. Fixed to `Carbon::createFromFormat('Y-m-d\TH:i', $this->
  scheduleAt, config('app.display_timezone', 'Asia/Kolkata'))->utc()`,
  matching the pattern `AttendanceController::storeCorrection()` already
  used for its own check-in/check-out time fields — the reference
  implementation was one file away and just hadn't been reused here.
  Also fixed the scheduler's default pre-filled time (`openScheduler()`'s
  `now()->addMinutes(30)`), which had the identical bug in reverse.
  Existing Create Meeting tests never caught this because they built
  their test input via `now()->addHour()->format(...)`, which
  round-trips through the same buggy parse and "accidentally" agrees
  with itself — new regression test hardcodes a literal
  `"2026-07-29T12:00"` input and asserts the exact UTC instant
  (`2026-07-29T06:30:00+00:00`) sent to Google's API. **The already-
  created wrong ADTA Group event is not retroactively fixed by this** —
  flagged to the owner, since correcting a real client-facing Calendar
  event that already sent real invites is not something to do
  unprompted. Direct push to master (`aed3540`, no PR), deployed same
  session.
- **2026-07-29 — Eight bug reports/small features from one owner batch,
  all fixed same session.** Investigated each independently before
  writing any code (several turned out not to be what they first looked
  like):
  1. **Call log reminders never cleared once the client was actually
     contacted again.** `SendCallFollowUpReminders`/My Day/the "Pending
     follow-ups" filter all just checked `follow_up_at`, with nothing
     clearing it when a newer call to the same client/lead superseded it.
     Fixed in `CallLogController::store()`: a newly-logged call with
     outcome Connected or Follow-up Needed now nulls out any other
     pending (`follow_up_notified_at` still null) `follow_up_at` on the
     same `callable` — a failed attempt (No Answer/Busy) leaves the old
     reminder standing, since the client genuinely wasn't reached.
  2. **A Project Manager (`Project.owner_id`) couldn't see tasks their
     own assignees created**, if they weren't ALSO added as a project
     team member. `TaskController::index()`'s visibility scope and
     `TaskPolicy::isParticipant()` only checked `project.assignees`
     (team membership) and never `project.owner_id` (the actual "Project
     Manager" field, see the 2026-07-08 entry above). Added the
     `owner_id` check to both.
  3. **Support Dashboard was missing task totals** (Total/Pending/
     Overdue) — `supportStats()` had only ever computed ticket stats
     since the dashboard was first built (checked git history — not
     actually a regression, just never built). Added task counts scoped
     to the Support user's own tasks, mirroring `taskSummary()`'s shape.
  4. **"A standalone SEO project is auto-created" — turned out to be a
     display bug, not project creation at all.** A screenshot showed a
     Task ("Technical SEO setup") with "Project: Standalone." Traced
     every Project-creation path (the create form, "Create Project from
     Deal") and found none can run unattended — so root-caused via
     production data instead of guessing: `Project` uses `SoftDeletes`,
     and `ProjectController::destroy()` just calls `$project->delete()`
     (soft) with no task cleanup — deliberate, matching this app's
     "preserve history" convention for every other soft-deleted parent.
     But a soft delete never fires the `tasks.project_id` foreign key's
     real `ON DELETE CASCADE` (confirmed the constraint IS active in
     production via `SHOW CREATE TABLE tasks` — it only fires on an
     actual `DELETE`), so a deleted project's onboarding tasks survive
     with their real `project_id` intact, while `$task->project`
     (Eloquent, excludes trashed rows by default) resolves to null —
     indistinguishable from a genuinely standalone task. Confirmed via
     production: 8 real orphaned tasks across 2 deleted projects ("SEO
     updates," deleted 2026-07-07; "SEO for ADTA Group," deleted
     2026-07-27 — the exact one in the screenshot). Fixed with a new
     `Task::projectLabel()` (Standalone only when `project_id` is
     genuinely null; "Project removed" when it's set but the project
     resolves to null) — same "relabel a real removed record, don't
     conflate it with something that never existed" convention as the
     soft-deleted-client fix elsewhere in this app. Nothing deleted or
     backfilled — the 8 orphaned tasks are legitimate historical records
     once correctly labeled.
  5. **Weekly GMB (and other recurring maintenance) tasks were landing
     on a Sales rep (Kiran).** `DispatchScheduledTasks::resolveAssignee()`'s
     fallback chain (Support assignee → lead-role assignee → project
     owner) had no role check at the final two steps — and a Sales rep
     is frequently a project's `owner_id` simply because
     `CreateProjectFromDeal` defaults it to the deal owner who won it.
     Fixed so the chain now skips Sales at every step; if nobody
     appropriate is left, the task is skipped for that project rather
     than routed to Sales.
  6. **Projects belonging to an Inactive client still showed in the
     project list.** Added a filter to `ProjectController::index()` —
     but the first pass (`whereHas('customer', '!= Inactive')`) also
     hid a project whose client was soft-deleted entirely, breaking the
     established "Client removed" convention (`customer()`'s default
     scope already excludes trashed customers from `whereHas`). Fixed
     with `whereDoesntHave('customer', '= Inactive')` instead — only
     excludes a customer that genuinely still exists with that exact
     status; a deleted customer's project stays visible with its
     existing "Client removed" label.
  7. **Added a "Total Leads" counter** to the Admin/Manager dashboard,
     alongside the existing Total/Active/Inactive Clients cards.
  8. **Made the Business Overview "Total outstanding" tile clickable**,
     linking through to the Receivables Report — the same treatment
     already given to the Accounts dashboard's Overdue Invoices/Collected
     tiles.
  All 8 covered by new/updated Pest tests, full suite (1338, up from
  1319) green, Pint clean.
- **2026-08-05 — AI Usage Report now includes wadesk.in as a fourth
  cross-app source (CRM + Drishti + SMDost + Wadesk), reusing the
  existing pull pattern rather than building anything new.** wadesk.in
  (the team's WhatsApp dashboard, separate app/repo) shipped its own
  AI feature this session — an after-hours assistant that auto-replies
  on the Marketing WhatsApp line using Claude — and the owner created it
  a dedicated Anthropic API key, separate from this CRM's. Anthropic's
  own Console already separates cost/usage per key with zero extra work,
  but the owner asked for it inside this CRM's existing AI Usage Report
  too, for one combined view. `AiUsageMetrics::drishtiUsage()`/
  `smdostUsage()` already established the pattern for exactly this: poll
  the other app's own `GET /api/ai/usage` (`X-Service-Key` auth) and fold
  the totals into the report/CSV/budget calc, degrading to "Unavailable"
  if unreachable — so `wadeskUsage()` is a straight third mirror of that
  same `fetchAppUsage()` helper, reusing the `services.wadesk.base_url`/
  `service_key` config that already existed (from the Tier 3 WhatsApp
  integration) with zero new CRM-side config or secrets. `budgetStatus()`
  gained a 4th optional `$wadeskCostPaise` param; the Blade view's
  "Cross-app usage" table gained a Wadesk row.
  **Real gotcha caught while building wadesk's side of this** (wadesk.in
  had never tracked its own AI usage at all until this feature — its
  `generateAiReply()` discarded Anthropic's token-usage data from every
  response): Prisma's `aggregate()` with `_count: true` returns a
  breakdown object (`{_all, id, feature, ...}`), not a plain number.
  Forwarding that raw would have made this CRM's `(int) $totals['_count']`
  cast the whole object down to `1` regardless of real call volume —
  caught before shipping by checking wadesk's actual generated Prisma
  types, not assumed from Drishti/SMDost's identical-looking contract.
  wadesk's endpoint now explicitly flattens to `_count: totals._count._all`
  before responding. Wadesk's hardcoded per-token pricing ($1/$5 per
  million for Haiku 4.5, matching this file's own
  `services.anthropic.pricing`) must be kept in sync by hand across the
  two repos — there's no shared config between them.
  Full suite (1448, up from 1424) green except one pre-existing,
  unrelated failure confirmed on a clean `master` checkout before this
  work started (`ManagementReportsTest`'s attendance-% test is
  date-dependent and fails early in any calendar month — not touched by,
  or related to, this change). Pint clean.
- **2026-08-12 — Resource Library + role-gated Important Links (#115), and
  Sidebar grouping + active-highlight fix (#116), both shipped and
  deployed same session.** Owner asked for a place for Support/Accounts
  staff to find shared internal files (plugin builds, GST certificates)
  and for links to be genuinely role-restricted by department, plus
  separately for the sidebar (38 flat items, no working active-item
  highlight) to be grouped and fixed. Investigated first: `important_links`
  + `ImportantLinksManager` already existed with `department`/`purpose`
  fields that were display-only labels, not actual access restriction —
  the real gap vs. the ask. Built via `/plan`, confirmed via
  AskUserQuestion: retrofit real role visibility onto Important Links too
  (not just new Files), combine Files+Links on one "Resources" page (two
  tabs) rather than two sidebar entries, fixed `TeamResourceCategory`
  enum, ship sidebar grouping as its own separate PR.
  New `App\Models\Concerns\HasRoleVisibility` trait (mirrors
  `menu_item_role`'s shape via two small pivots, `team_resource_role` and
  `important_link_role`) shared by the new `TeamResource` model and the
  retrofitted `ImportantLink` — no visibility rows = visible to everyone
  (non-breaking default for every existing link), Admin/Manager always
  bypass, role matching via `allRoles()` (same union rule as the Menu
  Controller sidebar, not the dashboard-panel's primary-only rule). Menu
  key deliberately left as `important-links` even though the page moved
  to "Resources" (`resources.index`) — `MenuItemsSeeder::run()`'s
  `updateOrCreate` matches by key, so renaming it would have orphaned the
  existing role-assignment pivot rows into a duplicate row instead of
  updating in place.
  Sidebar: new display-only `menu_items.group` column +
  `App\Enums\MenuGroup` (6 cases, formalizing `MenuItemsSeeder`'s own
  existing workflow-stage-ordering comment) render as collapsible
  sections (plain `x-show`/`x-transition` — no `x-collapse` plugin
  installed — collapse state persisted per-browser via `localStorage`,
  same pattern as the announcement-banner's dismiss). The active-highlight
  bug: `request()->routeIs($item->route)` was already present, just
  exact-match-only, so a lead's detail page never highlighted "Lead
  Generation." Fixed via `MenuItem::activePatterns()` — exact route name
  plus a wildcard on the first route-name segment, **except** routes
  under the shared `reports.` namespace (backs ~12 distinct report pages
  but only 2 sidebar items, Account/Collections), which get exact-match
  only so they don't cross-highlight each other or unrelated report
  pages — a real collision a naive wildcard-everything approach would
  have hit.
  Both PRs built/tested/Pint-clean independently (branched off `master`,
  no shared files, by design), then owner said "merge it" — clarified via
  AskUserQuestion this meant both. #116 had a genuine merge conflict with
  #115 (both edited `MenuItemsSeeder.php`) — resolved by hand, full suite
  re-verified (1850 green) before the final push. Deployed same session
  (owner said "deploy it"): rebuilt+committed frontend assets first (the
  sidebar's new `-rotate-90` collapse-chevron utility wasn't used
  anywhere else in the app, so it was missing from the previously-shipped
  `public/build` — same class of gotcha as the Help-page one already
  documented in [[deployment]]), then the standard migrate+reseed+cache
  sequence, verified against real production data (not just HTTP status)
  via the scratch-script pattern.
  **New standing default, confirmed with the owner**: after any shipped
  change that's genuinely staff-facing (a new page, a moved/renamed
  feature, a workflow change — not a bug fix, backend refactor, or
  anything invisible to a user), draft a Notice Board title/body/audience
  and present it for the owner to paste in via Notice Board → New
  Announcement, without waiting to be asked. Never post it directly —
  Notice Board creation goes through the real 2FA-gated admin login,
  which stays the owner's alone; a direct-database-write attempt to skip
  that was correctly blocked by the permission system when first tried.
  Keep the body a single flowing paragraph (no bullets/line breaks — the
  `<x-announcement-banner>` component renders `body` via plain `{{ }}`
  escaping, so manual line breaks collapse visually into one run-on line).
- **2026-08-13 — Lead Assignment Rules + Reassign action + deactivation
  handover, one combined milestone.** Originated from the owner noticing
  Kiran's leads were visible to other Sales reps — investigated first, not
  assumed a bug: `Lead::scopeVisibleTo()`/`LeadPolicy::view()` deliberately
  let every role see every lead (documented, "Keep in sync" comment already
  present) — a shared-visibility design, not a leak. The real ask underneath
  was that all new leads (including an upcoming "CRM & ERP" Meta ad) only
  ever route via `LeadObserver::autoAssign()`'s least-loaded round-robin,
  with zero way to pin a campaign or service line to a specific rep, plus a
  separate ask for reps to hand off their own leads when someone's on leave
  or has left. Confirmed 3 design decisions with the owner via
  AskUserQuestion before building: rules match on **both** campaign name and
  service (campaign taking priority when both could apply to the same
  lead — durable against future campaign renames since a service rule
  doesn't need re-creating every ad version, while a campaign rule still
  allows one-ad-specific targeting); "CRM & ERP" folds into the existing
  **Software Development** service rather than a new 9th service line (no
  schema/reporting change needed for a first campaign); and to build all
  three pieces (rules, reassign, deactivation handover) as one PR.
  New `lead_assignment_rules` table (`utm_campaign` XOR `service_id`,
  `assigned_user_id`, `active` — exactly one of the two match columns per
  row, enforced in `LeadAssignmentRuleRequest`, not the schema).
  `LeadObserver::autoAssign()` now checks `resolveRuleAssignee()` (campaign
  match, then service match) before its `resolveLeastLoadedSales()`
  fallback (same query, just extracted) — and **re-checks the rule's target
  is still an active Sales user at match time**, so a rule whose target was
  later deactivated or role-changed silently falls through to round-robin
  instead of assigning to someone ineligible, mirroring the existing
  active-Sales-only constraint rather than only enforcing it at rule-creation
  time. New Admin/Manager page (`lead-assignment-rules`, sidebar under Admin
  & Config, no dedicated Policy class — same no-Policy convention as
  Services/Festivals) follows Services' own inline-edit-table shape; per the
  Payment inline-edit precedent (date/mode/reference editable in place,
  amount needs delete-recreate), only a rule's assigned rep/active status
  are editable in place — changing what it *matches* needs delete-and-recreate,
  avoiding a much fussier per-row Alpine toggle for a rarely-changed field.
  **Real bug caught by a test before shipping**: the update route's
  uniqueness check originally read `$this->route('lead_assignment_rule')`
  (snake_case) while the route itself binds `{leadAssignmentRule}`
  (camelCase, matching this app's existing multi-word-model convention, e.g.
  `{leaveRequest}`) — the mismatch meant `route()` always returned null, so
  saving an existing active rule's assigned rep would false-positive against
  its own uniqueness check. Caught by adding a dedicated in-place-update
  test, not by inspection.
  New `App\Actions\ReassignLead` (single mechanism used by both the ad-hoc
  action and the bulk handover, so they log/notify identically). The
  reassignment reason is **not** a new column on `leads` — it's appended as
  a visible Note on the lead's existing timeline, since nothing in the app
  currently surfaces the `activities` audit trail in any view, and the
  point of capturing a reason is for the team to actually see it, not just
  have it exist in an unreachable table. `LeadPolicy::reassign()` gates
  reachability (Admin/Manager any lead; Sales only a lead they own);
  `LeadReassignRequest` separately restricts *who Sales can hand off to* —
  another active Sales peer only, never Admin/Manager, never themselves —
  since that restriction depends on the target, not just the lead. New
  `LeadReassignedNotification` deliberately isn't a reuse of
  `NewLeadNotification` — "New lead: X" would be a misleading, reason-less
  message for a lead that isn't new.
  Deactivating a Sales user (`UserController::update()`) who still owns open
  leads (`LeadStatus::openValues()`, a new static helper extracted from
  `autoAssign()`'s inline filter — now shared by three call sites instead of
  redefining "open" separately each time) now **requires** picking a
  handover target — surfaced as an amber panel on the Edit User form that
  appears when the Active checkbox is unticked, per the owner's explicit
  "not a silent no-op" framing. Deliberately validated as a hard requirement
  (`UserUpdateRequest::withValidator`), not just a suggestion — nothing
  before this closed the gap where a departed rep's open leads sat silently
  under an inactive owner with no prompt to move them, unlike every other
  soft-deleted/deactivated-record convention already in this app that
  either relabels or reassigns rather than leaving a dangling reference.
  Full suite (1888, up from 1850) green, Pint clean, migrated against local
  MySQL and smoke-tested end-to-end via curl (rule create+list, Reassign
  button rendering for a lead's owner) rather than assumed from passing
  tests alone.
- **2026-08-14 — Wadesk.in full conversation capture: every WhatsApp message,
  both directions, lands on the matching Ticket/Lead — not just a
  conversation's opening message.** Owner asked for wadesk.in chat messages
  to summarize onto the matching Lead/Client as notes, so the full
  communication journey is visible on one page. Investigated the real gap
  first, not assumed: `WhatsappWebhookController` only ever fired for the
  *first* message of a new/reopened wadesk.in conversation — a Ticket's
  every later message (both directions) was silently dropped (the dedup
  check returned `'duplicate'` and did nothing), and a Lead only captured
  later *inbound* messages as raw notes, never what a staffer typed back
  directly in wadesk.in's own UI (only a reply sent *from* the CRM ever
  reached wadesk.in — the reverse direction never existed). Fixed on both
  sides of the integration (this repo + `D:\Projects\Whatsapp Dashboard`,
  the wadesk.in/`whatsapp-dashboard` repo):
  - **wadesk.in side**: new `src/lib/crm-notify.ts` (extracted from the
    inline fetch the inbound webhook used to do) is now called on every
    inbound customer message (moved to fire after the message is actually
    saved, using its own row id — not Meta's `metaMessageId` — as the
    idempotency key), every human agent reply sent from `/api/send`
    (skipped for a CRM-originated service-key send — see below), and every
    AI after-hours auto-reply (`ai-assistant.ts`). Payload gained
    `message_id`/`direction`/`sender_type`/`sender_name` — all new fields,
    only ever sent by this updated build.
  - **CRM side (`WhatsappWebhookController`)**: every one of the four new
    fields is optional, defaulting to exactly what an *older* wadesk.in
    build already sends (`direction` → `inbound`, `sender_type` →
    `customer`, no `message_id`) — deliberately backward compatible, so
    deploying the CRM first (before wadesk.in) never breaks real inbound
    traffic in the gap between the two deploys, and the richer behavior
    below only activates once wadesk.in's own new build is live, no CRM
    redeploy needed. `sender_type: 'crm'` is a no-op early return — the CRM
    already recorded that message itself the moment it sent it, so an echo
    of its own send is discarded, not double-logged (in practice wadesk.in
    doesn't even bother calling for its own CRM-originated sends, but the
    CRM handles it defensively either way). A `message_id`-tagged call
    dedupes via a new `wadesk_message_logs` table (pure idempotency ledger,
    unique `wadesk_message_id`, nothing else reads it) — a legacy call with
    no `message_id` keeps the *exact* pre-existing "second call for an
    existing ticket → `'duplicate'`, do nothing" behavior, since an older
    wadesk.in build never genuinely calls twice for an open conversation
    (only for a literal retried delivery), so that old contract is provably
    still correct for the calls that still use it.
  - **Ticket**: when a conversation already has a Ticket, a new message
    (once carrying `message_id`) becomes a `TicketReply` instead of being
    dropped — new nullable `ticket_replies.whatsapp_direction`
    (`inbound`/`outbound`) and `external_sender_name` columns (a WhatsApp
    sender has no CRM `user_id` and no portal `Contact` row to fall back
    on). `TicketReply::isFromCustomer()`/`authorName()` extended to use
    these — which means `AiAssistant::summarizeTicket()` and the existing
    Ticket "Summarize thread" button correctly pick up the fuller history
    with **zero changes to either**, since both already branched on
    `isFromCustomer()`. A customer messaging again on a Resolved/Closed
    ticket now reopens it; a staff/AI outbound message never does.
  - **Lead**: `Note` gets no new column (a much more widely shared model,
    not worth a schema change for one channel) — an outbound message is
    instead prefixed `[Sent via WhatsApp by {name}]` /
    `[Sent via WhatsApp by AI Assistant (auto-reply)]` on the note body
    itself, keeping the existing inbound note format (no prefix) exactly as
    it was.
  - **New**: `AiAssistant::summarizeLead()` + a "Summarize" button on
    `RecordNotes` (gated `$record instanceof Lead`, mirrors
    `canDraft()`/`canReplyViaWhatsapp()`'s existing instanceof-gating
    pattern in that same shared component) — a Lead had no equivalent to
    the Ticket/Customer "Summarize" button before this, and now that its
    notes timeline is the actual full WhatsApp conversation (both
    directions), summarizing it is the direct answer to the owner's original
    ask. New `summarize_lead` feature key in `AiUsageMetrics::label()` so it
    shows up in the AI Usage Report like every other AI feature.
  30 new/updated Pest tests in `tests/Feature/Api/WhatsappWebhookTest.php`
  (all 20 pre-existing tests pass completely unchanged, confirming backward
  compatibility) plus `AiAssistantTest`/`AiAssistLivewireTest`. Full suite
  1964 green (up from 1949), Pint clean. wadesk.in side: `npx tsc --noEmit`,
  `npm run lint`, and `npm run build` all clean (that repo has no test
  suite — see its own CLAUDE.md).
