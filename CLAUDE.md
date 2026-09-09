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
- **2026-08-14 — Telegram lead alerts + WhatsApp payment confirmation for
  the Visibility Audit offer, both built as no-op-until-configured jobs
  mirroring `SendWhatsappHandoffMessageJob`'s existing contract.** Owner
  asked for (1) a Telegram alert whenever a new lead lands, and (2) a
  WhatsApp transactional confirmation to whoever pays via the Visibility
  Audit offer (`/offers/visibility-audit`), on the Marketing line
  (`9112095202`). Confirmed via AskUserQuestion: Telegram posts to **one
  shared group** (not a per-Sales-rep DM — simpler, no new per-user
  "connect Telegram" step or `chat_id` column needed). New
  `App\Jobs\SendTelegramLeadAlertJob`, hooked into the existing
  `LeadObserver::notifyNewLead()` path (already fires on every Lead
  creation, already knows the resolved owner) — plain Telegram Bot API
  `sendMessage` HTTP POST, no polling/webhook needed to send outbound. New
  `App\Jobs\SendVisibilityAuditPaymentConfirmationJob`, dispatched from
  `RecordVisibilityAuditPurchase` after a purchase row is created — same
  `wadesk.in` `POST /api/send-template` call shape as the existing Deal-Won
  handoff job, just the Marketing number instead of Support and a new
  `WADESK_VISIBILITY_AUDIT_TEMPLATE_NAME` config (a suggested two-variable
  template body — payer name, tier+amount — is documented alongside the
  config value for the owner to submit to Meta). Applies to **any**
  Visibility Audit tier (Gbp/Website/Both), not just the Gbp one literally
  named in the request — a payment confirmation reads the same regardless
  of which tier was purchased, and `RecordVisibilityAuditPurchase` already
  treats all three tiers identically. Both jobs are true no-ops (log a
  warning, never throw) until their respective env vars are set — ship
  inert today, Telegram starts working the moment the owner creates a bot
  via @BotFather and sets `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`; the
  WhatsApp confirmation starts working once the template is Meta-approved,
  same as the handoff job's existing contract.
  **Real gap found while researching, not this milestone's scope but
  worth flagging**: `WhatsappWebhookController` only ever captures the
  *first* inbound message per wadesk.in conversation — for a Ticket it
  dedupes every later message away entirely (never becomes a reply), and
  for a Lead only inbound messages land as notes, never what staff type
  back inside wadesk.in's own chat UI. Scoped out of this milestone
  (owner confirmed via AskUserQuestion) as its own larger, cross-repo
  item — see [[backlog]].
  19 new Pest tests (dispatch + HTTP-call shape + every no-op branch for
  both jobs), full suite 1968 (up from 1949) green, Pint clean. No
  frontend/Blade changes, so no `npm run build` needed. Updated
  `docs/user-guides/admin.md` (new Section 13a) and
  `docs/user-guides/integrations.md` (Integration 8 extended, new
  Integration 11) plus `.env.example` — also filled in `WADESK_MARKETING_NUMBER`
  there, a pre-existing real gap (the var was used in `config/services.php`
  and production but never documented in `.env.example`), found while
  editing the adjacent block.
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
- **2026-08-16 — Quotation follow-up reminders + reseller billing
  (PR #121).** Two features in one milestone. (1) Sending a quotation now
  auto-creates a 3-day dashboard follow-up reminder, naming the referring
  partner in the reminder text when the client was referred (e.g. "Follow
  up with Prajakta Dahake (referring partner)...") — a plain reminder for a
  direct client otherwise, same mechanism. (2) `Partner` gets a nullable
  `billing_customer_id` (FK → `customers.id`) so a referral partner can
  instead be set up as a **reseller**: a referred client's quotations/
  invoices/recurring-invoice templates are GST-billed to the partner's own
  customer record instead of the client directly (e.g. Brand-Whiz's
  referred clients are billed to Brand Whiz, not each client's own GSTIN).
  New `Customer::billingTarget()` (`referringPartner?->billingCustomer ??
  $this`) is the single place this resolves — called wherever a
  Quotation/Invoice/RecurringInvoice is actually created
  (`QuotationBuilder`, `RecurringInvoiceBuilder`, `InvoiceController`), so
  the client someone *picks* in the UI and the customer actually GST-billed
  can differ without a second, redundant "bill-to" field anywhere. Deliberately
  going-forward only — existing invoices are untouched, no backfill.
  **Known follow-on gap, fixed in the very next milestone (see the 2026-08-16
  entry below):** because a reseller-billed invoice/quotation's `customer_id`
  becomes the billing customer, every existing partner-scoped query that
  filtered on `Customer.referring_partner_id` (the internal `/partners/{id}`
  page's Quotations table, the Partner Portal's own Quotations list, and the
  6-month `billedByClient()`/`billedByMonth()` breakdown) silently stopped
  matching a reseller partner's own billed work — not caught in this PR's
  own testing, surfaced later when building real account visibility for the
  Partner Portal.
- **2026-08-16 — Quotation tracking for referral partners, internal +
  Partner Portal (PR #122).** Added the internal `/partners/{id}` page
  (header, portal invite/revoke, commission section, "Billed — last 6
  months" via new `CollectionsMetrics::billedByClient()`/`billedByMonth()`,
  a Quotations table, and `clientHealth()`-driven "Client health") and gave
  referral partners a Partner Portal (guard `partner`, mirrors the client
  portal's `Contact` auth shape) to see their own referred clients,
  quotations (with PDF download), content-piece collaboration, and
  commission earnings — read-only, scoped per-partner via inline
  `abort_unless` ownership checks in each `PartnerPortal\*` controller (no
  dedicated Policy class for the `partner` guard, since every query is
  already pre-scoped to the logged-in partner). At the time this shipped,
  "Your Referred Clients" was intentionally minimal (name + status only) —
  see the entry below for why that turned out to be too minimal in
  practice.
- **2026-08-16 — Partner Portal: real account visibility for referred
  clients, plus the reseller-billing visibility fix flagged above (this
  milestone).** Owner reported the Partner Portal dashboard (real partner
  Prajakta Dahake's screenshot) was a dead end: "Your Referred Clients" was
  just a name+status list with no way to see receivables/pending payments
  so a partner could actually follow up with their own referred clients,
  and asked what "Your Content Submissions" even does. Confirmed 3 scope
  decisions with the owner via AskUserQuestion before building: (1) full
  invoice-level detail per referred client (number, amount, due date,
  status, overdue days), not a summary-only figure; (2) applies to ALL
  referred clients, not just reseller-billed ones; (3) "Your Content
  Submissions" stays fully staff-gated (a partner still can't originate a
  submission, only upload against one staff already opened) — just improve
  the empty-state copy, since that's working as designed
  ([[project-progress]] / the Content Collaboration module), not a bug.
  The underlying receivables data already existed — `CollectionsMetrics::
  clientHealth()`/`billedByClient()`/`billedByMonth()` already powered the
  internal Partner page added one milestone earlier — this exposes the
  same data to the `partner` guard rather than inventing new billing
  computation.
  **Fixed the reseller-billing visibility gap surfaced by the prior two
  milestones**, on both the internal Partner page and the Portal: new
  `Partner::quotations()`/`ownsQuotation()` match a quotation via EITHER
  the referred client's own `referring_partner_id` OR (reseller partners
  only) `customer_id === billing_customer_id`, so a reseller partner's own
  quotations are no longer invisible to themselves and to internal staff
  viewing their Partner page. Deliberately did NOT try to attribute a
  reseller partner's consolidated invoices back to individual referred
  sub-clients — that would be dishonest: a reseller-referred client's
  invoices are genuinely GST-billed in bulk to the partner's own
  `billing_customer` record (`Customer::billingTarget()`), with no
  per-sub-client amount ever recorded anywhere in the schema. Instead, new
  `CollectionsMetrics::accountSummaryForCustomer()` (invoice list +
  outstanding total + overdue count for one Customer) powers a new "Your
  Account" section — shown only when `billing_customer_id` is set, against
  the partner's own `billingCustomer()` — on both the internal Partner page
  and the Portal dashboard; each referred sub-client's own row is
  correctly billed "Billed via your account" rather than a misleading ₹0.
  For the common, non-reseller case (confirmed as the actual scenario in
  the owner's screenshot), `accountSummaryForCustomer()` runs directly
  against each referred client's own `Customer` record — which already
  carries its own real invoices, so full per-client detail Just Works.
  New Portal route `partner-portal/clients/{customer}` (`PartnerPortal\
  ClientController`, same inline `abort_unless($customer->
  referring_partner_id === $this->partner()->id, 404)` ownership pattern
  as the rest of the Portal — reseller partners' `billingCustomer()` is
  deliberately NOT reachable through this per-client drill-down, since
  it's not "a referred client," it's the partner's own account, shown
  directly on the dashboard instead) shows a referred client's own
  invoices, quotations, and active projects — the answer to "give the
  overall details and answers to all the questions the partner needs to
  know," not just a name in a list.
- **2026-08-17 — Employee Activity Timeline (PR #131): Phase 1 of a new
  owner-driven direction — turn the CRM into a productivity/KRA/AI-coaching
  assistant for every employee, not just a system of record.** Owner's own
  framing: "this CRM is just not a simple CRM anymore... it is going to be
  an assistant or guide for an employee," wanting every employee's CRM
  activity captured so AI can eventually help with productivity, targets,
  and coaching. Confirmed a 3-phase build order via AskUserQuestion before
  writing any code: (1) Activity Timeline — a unified view of what someone
  did/still has pending, (2) a generalized per-role KRA/Target framework
  (this file's next entry), (3) AI coaching tied to those targets, each
  phase deliberately deferred until the previous one's data/design is
  proven rather than planned all at once.
  New `App\Services\EmployeeActivityTimeline`: `entries()` (chronological
  "what did they do" feed, sourced from the existing `activities` audit
  log — already on ~30 models via `LogsActivity` — plus `CallLog`, which
  isn't activity-logged) and `pending()` ("what's still open" snapshot:
  tasks, tickets, leads, deals, quotations awaiting a client decision,
  unpaid/overdue invoices). Zero new data capture — confirmed with the
  owner that staff shouldn't have to remember a new step; this is pure
  aggregation over what the app already logs. Quotations/invoices have no
  owner column of their own, so both are attributed via the deal owner,
  falling back to the customer's account owner (confirmed via
  AskUserQuestion) — added `Invoice::ownerId()` mirroring the pre-existing
  `Quotation::ownerId()`, and refactored `Invoice::booted()`'s own
  notification lookup to reuse it instead of duplicating the same query.
  Surfaces: **Employee 360°** (Admin/Manager, any employee — both panels,
  "Activity timeline" has an independent date-range picker defaulting to
  today) and **Daily Reports** (self-view, every role, confirmed as the
  right home over My Day since it already served the same "what did I do
  today" purpose — same two panels, single-date picker browsable to a
  previous day, never future).
  **Caught proactively, not from a bug report**: date-range/date inputs
  parsed via `Carbon::createFromFormat(..., config('app.display_timezone'))`
  rather than `Carbon::parse()` (which resolves against `app.timezone` =
  UTC) — the exact off-by-5:30 bug class already hit once in this app
  (Create Meeting, 2026-07-29), recognized from that precedent before it
  could ship a second time.
  22 new/updated Pest tests, full suite 2086 green, Pint clean. Live-
  verified against real local MySQL dev data (not SQLite) before shipping.
  Deployed same session: `git pull` + `view:clear` + `view:cache` only (no
  migration/route/menu change — the entire feature is aggregation over
  existing tables). `gh pr create` and even a direct REST `POST .../pulls`
  both 503'd for several minutes (a genuine GitHub-side outage on write
  operations, reads worked fine) — owner opened PR #131 manually via the
  compare-URL fallback instead of waiting it out.
- **2026-08-18 — Generalized KRA/Target framework: one target metric per
  non-Sales role, a new `role_targets` table separate from `sales_targets`.**
  Phase 2 of the direction above. `SalesTarget` already gave Sales a
  single money target (deal value) with a progress bar; the real gap was
  that Support/Accounts/Intern/Telecaller had no domain-specific KRA at
  all — `ReportMetrics::ROLE_WEIGHTS` ranks them on generic tasks/
  attendance, with no ticket-resolution or collections figure anywhere.
  Confirmed the per-role metric mapping via AskUserQuestion before
  building: **Support** = tickets resolved (`Ticket.resolved_at`,
  Resolved-or-Closed — mirrors `DraftMonthlyWinsNote`'s own existing
  query exactly, not a new convention); **Accounts** = collections
  recorded in ₹ (`Payment.recorded_by` + `paid_on`); **Intern** = tasks
  completed; **Telecaller** = calls made — each reusing data the app
  already tracks, zero new capture. New `App\Enums\TargetMetric` is a
  bounded registry (`forRole()`/`role()`), not an admin-configurable
  field — same discipline as `CrmQueryCatalog`/`NudgeAutoDetectType`.
  **Deliberately did NOT widen `sales_targets` itself** to add a `metric`
  column — that table is load-bearing for Incentives/Partner Commission,
  and migrating it for this would have risked a financially-sensitive
  feature for no real benefit. New `role_targets` table instead, same
  shape (`user_id` nullable = role-wide target, `period_type`/
  `period_start`, same distinct-NULLs uniqueness caveat as
  `sales_targets`'s own migration comment). New `App\Services\
  RoleTargetMetrics` (`actualValue()`, `progressForUser()` — primary role
  only, same convention as `DashboardController`'s panel selection —
  and `teamRows()`) and `App\Http\Controllers\RoleTargetController`
  mirror `SalesTargetController`'s shape (blank field never zeroes an
  existing target). New **Team Targets** page (`role-targets.index`,
  sidebar under Team Insights, Admin/Manager only, confirmed via
  AskUserQuestion as its own page rather than folding into Employee 360°
  so there's one team-wide view per role) lists all 4 roles' active
  people with editable targets + progress in one place; Sales keeps its
  own separate Sales Dashboard page unchanged. Each person also gets a
  "Your target this month" progress bar on their own Dashboard panel
  (new `target_progress` key in `DashboardWidgets`, hideable like any
  other widget) — new reusable `<x-target-progress-bar>` Blade component
  powers both surfaces, though the Sales Dashboard's own pre-existing
  inline progress-bar markup was deliberately left as-is rather than
  refactored to use it, since that page already works and touching a
  financially-visible page wasn't this milestone's job.
  **Real pre-existing bug discovered while testing, not introduced by
  this work, deliberately not fixed here**: several unrelated test files
  (`DailyPrioritiesDigestTest`, `WeeklyOwnerDigestTest`,
  `SendProjectUpdatesDigest`/`DraftProjectDailyUpdate` tests, plus 3
  already-existing `DailyReportTest` tests) build "today" fixtures with
  bare `now()`/`today()` (UTC) while the commands they test correctly
  compute "today" via `Carbon::today(config('app.display_timezone'))`
  (IST) — surfaced only during the ~5.5-hour daily window where UTC and
  IST disagree on the date (confirmed live: wall clock was `2026-08-17
  18:47 UTC` / `2026-08-18 00:17 IST` when this ran). None of the 8
  failures were in this milestone's own files. Flagged to the owner
  rather than silently fixed, since it touches several unrelated test
  files outside this milestone's scope — see [[backlog]].
- **2026-08-18 (same day) — AI coaching upgraded to be target-aware: Phase
  3 of the productivity/KRA/AI-coaching direction, closing out the
  3-phase plan.** Both existing AI coaching surfaces —
  `AiAssistant::suggestProductivityImprovement()` (the self "✨ Get tips to
  improve" button on `MyProductivity`) and `suggestTeamProductivityGaps()`
  (the admin/manager "✨ Suggest Improvements for the Team" button on
  `ProductivityGapSuggestions`) — now fold in `RoleTargetMetrics::
  progressForUser()`'s target-vs-actual figure when the person's role has
  one (Support/Accounts/Intern/Telecaller), and both system prompts were
  rewritten to make the concrete target gap the centerpiece of the advice
  over the relative percentile whenever one is present, instead of
  silently ignoring the new target data introduced by Phase 2. New shared
  private `AiAssistant::targetLine()` formats a `progressForUser()` row
  into one prompt fragment — written once so both call sites describe a
  target identically rather than drifting.
  **Deliberately did NOT extend this to Sales** — Sales already has its
  own separate `SalesTarget`/pipeline-KPI mechanism (monthly + FY,
  dependent on `SalesPipelineMetrics::kpis()` being computed first), and
  wiring it into the same shared `targetLine()` shape would have meant
  either duplicating that computation or coupling two independently-
  evolving target systems together for a single prompt line — matches the
  same "Sales keeps its own mechanism" boundary drawn throughout Phase 2,
  not a fresh decision.
  **Deliberately did NOT extend this to `AiAssistant::
  summarizeTeamPerformance()`** (the broader narrative "AI Summary" on the
  same report page) — that's a trends/standouts summary across the whole
  team, a different shape of feature from per-person coaching toward a
  number, out of scope for "coaching," not silently missed.
  Enrichment happens at the two existing call sites, not inside
  `ReportMetrics`/`RoleTargetMetrics` themselves, so those services stay
  pure aggregation with no AI-shaped concerns: `MyProductivity::mount()`
  merges the viewer's own `progressForUser()` onto `$this->row['target']`;
  `ReportController::employeePerformance()` now injects
  `RoleTargetMetrics` and batch-fetches every visible row's `User` once
  (not N+1) to merge `'target'` onto each row before handing the array to
  `ProductivityGapSuggestions`.
  6 new Pest tests asserting on the actual HTTP request body sent to the
  (faked) Anthropic API — confirms the target line reaches the model,
  not just that the UI renders — full suite 2101 green (the same 8
  pre-existing UTC/IST-window failures from the entry above, still
  present, still none in this work's own files). Pint clean. Docs: a
  one-line addition to the existing "Get tips to improve" mention in
  `support.md`/`accounts.md`/`intern.md`, and to "Suggest Improvements for
  the Team" in `manager.md` — 3 PDFs regenerated (the ones with an actual
  handout; Intern has none, a pre-existing gap noted in Phase 2's entry,
  not touched here).
- **2026-08-24 — Fixed: a "Quotation needs approval" notification never
  reflected another admin/manager having already approved/rejected it.**
  Owner reported (screenshot) still seeing "Quotation needs approval: NSS
  Business Group" after Manager Manali had already approved it.
  `QuotationController::approve()`/`reject()`/`requestChanges()` only ever
  updated the quotation's own `approval_status` — they never touched any
  other recipient's copy of the `QuotationNeedsApproval` database
  notification broadcast to every Admin/Manager, so a resolved quotation
  kept showing an identically-worded, identically-clickable "needs
  approval" link to everyone who hadn't personally acted on it. Same
  "relabel a resolved/removed record instead of leaving a stale link"
  treatment already used for deleted invoices/deals/leads (2026-07-24/
  2026-07-29 entries above): `NotificationController::index()` now
  batch-checks the current page's `quotation_needs_approval` notifications
  against each referenced quotation's live `approval_status`, and the view
  renders a resolved one as plain text — `(approved by Manali Deshpande)` /
  `(rejected)` / `(changes requested by …)` — instead of a link. 3 new
  Pest tests in `tests/Feature/NotificationsTest.php`; full suite 2314
  green (one pre-existing, unrelated `MeetingRequestTest` IST-window
  flake, not this change — see [[feedback-gotchas]]). Pint clean. Direct
  push to master (`7b4e528`, no PR — one-file-class bug fix, same
  precedent as the portal-invite-404 and notification-dead-link fixes).
- **2026-08-25 — Leave Management: Team Leave Records (full, filterable
  history) + a Pending/Approved/Rejected/Currently-On-Leave summary strip
  for Admin/Manager; Cancelled is now a visible status, not a hard
  delete.** Sourced from a sales/leadership requirements doc audited item
  by item against the actual codebase first (a Claude Artifact gap
  analysis, not assumed from the doc's own framing of what "currently"
  exists) — the apply→approve/reject→notify pipeline, `reviewed_by`/
  `reviewed_at`/`review_notes`, and the pending-only `/leave-requests/
  approvals` queue already existed; what the doc actually asked for and
  didn't exist yet was the reporting layer. New `LeaveRequestController::
  team()` (`/leave-requests/team`, same `viewApprovalQueue` Policy gate as
  approvals — a browse/filter view with no approve/reject actions of its
  own, deliberately distinct from the approvals queue) filters by
  employee/type/status/date-range. New `App\Services\LeaveRequestMetrics::
  summary()` computes the four-count strip once, shared by both the
  approvals queue and the new team page so they can never silently
  disagree on the same numbers — same "single source of truth" precedent
  as `CollectionsMetrics::outstandingInvoicesQuery()` (2026-07-24 entry
  above). `LeaveRequestStatus` gained a `Cancelled` case;
  `LeaveRequestController::destroy()` now relabels to Cancelled instead of
  deleting the row, so a cancelled request stays permanently visible in
  the employee's own history — same "relabel a real record instead of
  making it disappear" convention used throughout this app (soft-deleted
  clients/projects, stale notification links, etc.), applied here to a
  request that was never soft-deleted at all, just silently removed.
  19 Pest tests (10 new, covering the team-history filters, the summary
  strip's counts, and the cancelled-stays-visible behavior), Pint clean.
- **2026-08-25 — Dashboard: Ongoing Projects + Upcoming Payments & Renewals
  widgets, plus a per-project Client Requirement Status field.** Last of the
  requirements doc's 7 items to be genuinely unbuilt (audited via a Claude
  Artifact gap analysis — 5 of the 7 items turned out to already be built
  when re-checked against `git log` mid-session, including three the
  artifact itself had called "Not started"). Reused `ProjectHealthMetrics::
  healthByProject()` (already existed at `/project-health`) and a new
  `CollectionsMetrics::upcomingPaymentsAndRenewals()` rather than building
  new aggregation from scratch. `healthByProject()` gained a `current_task`
  key (soonest-due not-Done task, undated tasks sort last, tie-broken by
  creation order) — the doc's "current task / employee assigned" ask,
  which the existing `/project-health` page never showed. `Project` gained
  `requirement_status` (reuses `App\Enums\DeliverableStatus` — Pending/
  Submitted/Received — rather than a near-duplicate enum, since
  `ProjectDeliverable` already uses the exact same three values for its own
  per-item status), nullable-on-write to stay backward-compatible with
  every existing project-form test, defaulting to Pending at the DB level.
  `upcomingPaymentsAndRenewals()` merges outstanding-invoice due dates
  (via the existing `outstandingInvoicesQuery()`) with active recurring
  templates' `next_run_on`, excluding PartnerCollects clients (NEDS never
  actually generates a real invoice for those — `RecurringInvoice::
  scopeDue()`'s own exclusion, applied here too so the widget never shows
  a "payment due" date that will never really bill), bucketed Overdue/
  7d/30d/60d by comparing each row's date to today. Both widgets follow
  the existing hideable-widget convention (`DashboardWidgets` catalog,
  `pending_approvals`'s own 2026-08-24 precedent) rather than always-on
  additions. **Real Blade gotcha hit while building**: an inline
  `@php($project = $row['project'])` directive, nested 4 levels deep
  inside Livewire's ExtendBlade precompiler wrapping (`@if` > `@if` >
  `@if` > `@foreach`), compiled without its closing `?>` — silently
  corrupting everything after it in the compiled file into raw (unparsed)
  PHP until a later stray `<?php endif; ?>` finally threw a parse error
  far downstream, `View: ...admin.blade.php`. The identical inline form
  works fine at shallow nesting elsewhere in this app (`project-health/
  index.blade.php`) — switching to the block form (`@php ... @endphp`)
  fixed it outright. Worth remembering if a future deeply-nested widget
  hits a mysterious "unexpected token endif" — check for an inline
  `@php(...)` first before assuming the `@if`/`@endif` count is wrong.
  17 files changed, 12 new Pest tests, full suite 2354 (up from 2338)
  green except the one pre-existing unrelated `MeetingRequestTest`
  IST-window flake (see [[feedback-gotchas]]). Pint clean, migrated
  against local MySQL. `docs/user-guides/manager.md` updated (new
  bullets in the dashboard list + the Projects section) and its PDF
  regenerated — the other 8 PDFs `make-handouts.mjs` also touches were
  discarded via `git checkout --` since their source `.md` files hadn't
  actually changed (headless-Chrome PDF generation isn't byte-stable
  even for unchanged content, so a blind `npm run handouts` re-run always
  dirties every PDF regardless of what actually changed).
- **2026-08-25 — Client Profile: Services tab overhaul (requirements-doc
  item #4, the last of the 7 — everything else was already shipped earlier
  this same day; see the Dashboard-widgets entry above).** Four sub-
  features, all additive: service-wise employee assignment for retainer
  services with no `Project`, service-specific links, a categorized Client
  Assets & Documents library with real version history, and per-client-
  service Client Requirements tracking. Planned via 3 parallel Explore
  research agents (Attachment/TeamResource/file-upload patterns,
  Client Profile/Customer/Service model shape, ProjectDeliverable/Policy/
  Livewire-CRUD conventions) before writing any code, then 3
  AskUserQuestion decisions confirmed before the plan was finalized: real
  version history for Assets (not a bare replace — new `client_asset_versions`
  table, the first file-versioning precedent anywhere in this codebase);
  free-text link labels, not a curated per-service-type field list (this
  app has already been burned once by a service-taxonomy rename — 2026-07-06
  Google Ads → Performance Marketing — so a label-suggestion map keyed to
  service identity was deliberately avoided); and the new employee
  assignment only *drives* the "who's working on this" display when a
  service has no live (non-Completed) `Project` — a project-backed service
  keeps showing its existing Project team, so a service never shows two
  competing "who owns this" answers.
  5 new tables (`service_assignments`, `client_service_links`,
  `client_assets`, `client_asset_versions`, `client_requirements` — one
  more than the artifact's original "4," since real versioning wasn't
  anticipated when that estimate was written). `ClientRequirement.status`
  and `Project.requirement_status` (this same day's earlier milestone)
  both reuse `App\Enums\DeliverableStatus` — the third reuse of that one
  enum rather than a fourth near-identical one. A received requirement's
  file is never a second, separate attachment — uploading it creates a
  `ClientAsset` and links it via `client_asset_id`, so it shows up in the
  Requirements checklist AND the Assets library from one upload.
  New `CustomerPolicy::manageServices()` (Admin/Manager/Sales/Support,
  unconditional — same role set and "shared client resource, not
  sales-owned relationship data" reasoning as the existing `manageLinks()`/
  `manageMeetings()`) covers every mutation across all four sub-features,
  deliberately one gate rather than four near-identical policy methods.
  Mixed implementation shape, each matched to precedent rather than forced
  into one paradigm: service assignment is a plain controller + inline
  Alpine `x-show` form (same "too small to justify a Livewire component"
  call as the 2026-07-24 Payment inline-edit fix — it's one dropdown per
  existing table row); service links, Client Requirements, and Client
  Assets are Livewire components (`ClientServiceLinks`/`ClientRequirements`/
  `ClientAssets`) mirroring `ImportantLinksManager`/`ProjectDeliverables`/
  `TeamResourceLibrary` respectively. Two new tabs (**Requirements**,
  **Assets**) on the Client Profile page; Service Links lives inside the
  existing **Services** tab (per-service links would make a mostly-empty
  standalone tab), and the Recurring Services table gained a **Team**
  column mirroring the Projects table's existing one — this also *is* the
  doc's "Team Working on This Client" ask, not a separate view, matching
  the doc's own framing ("trivial ... computed, not re-entered"). No
  sidebar/menu changes needed — `clients.show` was already reachable, same
  as every other per-client sub-feature in this app.
  **Same Blade gotcha hit twice more today, caught immediately from the
  earlier Dashboard-widgets entry's own note**: two more inline
  `@php($var = ...)` directives (the new Team column's `$liveProjects`/
  `$assignment` lookups, nested inside the Recurring Services table's row
  loop) silently broke Blade compilation the same way, corrupting
  everything downstream in `_services_tab.blade.php` into raw unparsed
  text until PHP hit a stray `<?php endif; ?>` far later and threw
  "Undefined variable $overdue" — from the unrelated Projects table
  further down the same file, not the actual broken line. Fixed the same
  way: block `@php ... @endphp` form. Recognized and fixed in one pass
  this time, in under a minute, specifically because the earlier entry
  documented the exact symptom to watch for — worth noting as a case
  where writing the gotcha down the first time paid off within the same
  session.
  Full suite 2377 (up from 2354) green, same one pre-existing unrelated
  `MeetingRequestTest` IST-window flake. Pint clean, migrated against
  local MySQL. `docs/user-guides/manager.md` updated (new Services-tab
  paragraphs), only `manager.pdf` regenerated (rest discarded per the
  established `npm run handouts` gotcha).
- **2026-08-26 — Two real bugs reported via screenshot, both fixed same
  session: the active sidebar item could be scrolled off-screen with no
  way to find it, and the Project Updates grouped views silently stayed
  scoped to "My Services."** Owner's own words: had to scroll down again
  every time just to see where they were in the sidebar, since it now has
  ~30 items across 6 groups; separately, clicking **My Services** (an
  admin who owns no projects, correctly empty) then **Client-wise**
  showed **no clients** — not a data bug, both buttons stayed lit purple
  at once.
  **Sidebar** (`resources/views/layouts/sidebar.blade.php`): the current
  page's own link now gets `data-active-menu-item`, and a small script
  (`@push('scripts')`, runs once on load) calls `scrollIntoView({block:
  'center'})` on whichever copy is actually laid out (desktop `<aside>`
  vs. the mobile overlay, checked via `offsetParent !== null`). Separately
  — and this was a real latent bug of its own, not just the reported
  complaint — a group the user had manually collapsed could hide the
  active item entirely with zero visual trace of "where am I"; the
  group's own `x-data="{ open: ... }"` now checks
  `$itemsInGroup->contains(...activePatterns())` first and forces `open:
  true` when the current page lives in that group, overriding (never
  persisting over) the stored collapse state for just that one page load.
  Deliberately did NOT force-collapse every OTHER group by default — that
  would silently override every user's own manually-chosen layout, a
  bigger behavior change than what was reported or asked for.
  **Project Updates** (`resources/views/projects/index.blade.php`): the
  Client-wise/Employee-wise/Service-wise links previously did
  `'mine' => $mine ?: null`, carrying the current My-Services scope
  forward — so switching from an empty My-Services view into any grouped
  view stayed silently scoped to "my own projects" (still empty) instead
  of resetting to a fresh, full grouped view. `mine` and `group` had never
  been a deliberately composable combination the UI exposed cleanly (My
  Services itself has no grouping option shown), so removing the carry-
  forward — making each button a clean, independent view switch — matches
  what clicking a different filter button reasonably means, and fixes
  both the empty-results bug and the two-buttons-lit-at-once confusion in
  one change (`ProjectController::index()`'s server-side `$mine`/`$group`
  handling was already correct; this was purely a frontend href bug).
  **Real Blade gotcha hit a third time, same session it was written
  down** (see [[feedback-gotchas]]): adding the new group-active `@php`
  block shifted the sidebar's nesting just enough to tip over a
  *pre-existing* inline `@php($active = ...)` two lines below it that had
  apparently been safe at the old nesting depth — caught immediately from
  the now-documented symptom (`storage/framework/views/*.php` full of
  literal, uncompiled `@if`/`@foreach` text) rather than chasing the
  reported "unexpected end of file" location, which pointed nowhere near
  the real cause.
  2 new Pest tests (sidebar force-open + scroll-script presence) + 1 new
  Pest test (group links never carry `mine=1`), full suite 2378 green —
  same 2 pre-existing unrelated flakes as before, plus one newly-noticed
  one (`EmployeeActivityTimelineTest`'s "sorts entries most recent first"
  — a `now()->subHours(3)` fixture that crosses the UTC midnight boundary
  when run between 00:00–03:00 UTC, same class of bug as the other
  documented time-window flakes, not caused by this change). Pint clean.
  No new Tailwind classes, no `npm run build` needed this time.
- **2026-08-26 (same day) — Two more owner-reported UI fixes: the Clients
  page toolbar was three stacked rows before any data showed, and the
  sidebar's own accordion fix from earlier the same day turned out to be
  incomplete — it force-opened the active page's group but left every
  OTHER previously-opened group open too, and the scroll-into-view script
  scrolled the whole page (not just the sidebar) because `<aside>` was
  never actually an independent scroll container.**
  **Clients toolbar** (`resources/views/clients/index.blade.php`,
  `02fc390`): search + Import CSV/Add Client on row 1, all 5 filter
  selects + sort auto-applying on `onchange` on row 2 (no separate Filter
  button), a "Clear filters" link that only appears once something other
  than the default Active-status view is active. Presented 3 layout
  options via AskUserQuestion (two clean auto-apply rows / one row with a
  Filters popover / one row with only Status+Owner visible) — owner picked
  the two-row auto-apply option as least dev work while keeping every
  filter one click away.
  **Sidebar accordion, take 2** (`resources/views/layouts/sidebar.blade.php`,
  `731cd51`): owner's own screenshots showed the actual failure —
  navigating to Project Updates landed the page scrolled down to the
  pagination row of a 59-result table, with both "Delivery & Support" AND
  a leftover "Team & Insights" both expanded in the sidebar. Root cause:
  `<aside>` only had `min-h-screen`, so on a page taller than the
  viewport it grew to match its very tall sibling (the main content
  column) instead of clipping — there was no real internal scroll
  container, so the scroll-into-view script's target search walked up to
  the nearest ACTUAL scrollable ancestor, which was the whole `<html>`/
  `<body>`, and scrolled the entire window. Fixed properly this time:
  dropped `localStorage` group-open persistence entirely (every
  navigation in this app is a real full-page load, never an SPA
  transition, so there's no reason a previous page's manual toggling
  should carry over) — exactly one group, the one containing the current
  page, opens on every fresh load, everything else starts collapsed, full
  stop. Removed the scroll-into-view script outright rather than trying
  to re-scope it — with an accordion sidebar (~10 items open at once,
  max), the active item is essentially always visible without scrolling
  anyway. `<aside>` is now `sticky top-0 h-screen` so it's a genuinely
  independent scroll column regardless, defense-in-depth against this
  exact bug class recurring from some future sidebar interaction.
  **Also split the 15-item Team & Insights group** (owner: "any main menu
  not having more than 10 menus in it") into **Team & Insights** (8 —
  Action Center, Approval Center, Project Health, Revenue at Risk, Client
  Radar, VA Funnel Analytics, Employee 360°, Team Targets — the
  monitoring/scoring dashboards) and a new **Team Tools** (7 — Team
  Workload, Manager Calendar, Daily Reports, Best Employee, Partners,
  Notice Board, Team Nudges — day-to-day team-management utilities). New
  `App\Enums\MenuGroup::TeamTools` case; `MenuItemsSeeder`'s
  `updateOrCreate` matches by `key` (unchanged for all 15 rows), so
  re-seeding only updates the `group` column in place — no orphaned pivot
  rows, matching the same precedent the 2026-08-12 sidebar-grouping work
  already established.
  4 new/rewritten Pest tests (accordion collapses every other group —
  counts `open: true` occurring exactly twice, once per desktop/mobile
  copy of the active page's group; the group split renders both new
  labels), full suite 2380 green (same one pre-existing unrelated
  `MeetingRequestTest` flake). Pint clean. `npm run build` run (new
  `md:sticky`/`md:h-screen` utilities), `php artisan db:seed
  --class=MenuItemsSeeder --force` re-run locally to apply the group
  split.
- **2026-09-03 — Lead visibility fix: Sales now sees only their own (or
  unowned) leads; Telecaller reversed from a shared no-ownership calling
  queue to real per-telecaller assignment.** Reported by the Sales team:
  Kiran and Mohit (both Sales) could see each other's leads. Checked the
  code, not assumed a leak: `Lead::scopeVisibleTo()`/`LeadPolicy::view()`
  deliberately let every role see every lead (documented "Keep in sync"
  comment, confirmed intentional once already on 2026-08-13 when the
  owner first noticed this for Kiran specifically). Owner this time chose
  to actually restrict it: `scopeVisibleTo()`/`view()`/`update()` now scope
  Sales to `owner_id = self OR owner_id IS NULL` (mirrors
  `Customer::scopeVisibleTo()`'s own established "additional roles only
  WIDEN access, never narrow" pattern — Admin/Manager always bypass).
  In the same conversation the owner also asked for Telecaller to see only
  leads "assigned to them specifically" — investigated first: Lead had NO
  per-telecaller field at all, only `owner_id` (always a Sales rep,
  per `LeadObserver::autoAssign()`), so this reverses the 2026-07-26
  "shared calling queue, no ownership" decision, not just a policy tweak.
  Confirmed 3 follow-on design decisions via AskUserQuestion before
  building, since scoping Telecaller with no transition plan would have
  silently zeroed out every telecaller's queue: (1) new leads auto-assign
  to a Telecaller too, via the same least-loaded round-robin pattern as
  Sales (`LeadObserver::autoAssignTelecaller()`/`resolveLeastLoadedTelecaller()`,
  new `User::telecallerLeads()` relation) — deliberately primary-role-only,
  matching the 2026-08-08 multi-role decision's own "auto-assignment stays
  single-assignee" precedent, so a Sales+Telecaller multi-role user is
  never an eligible round-robin target; (2) a one-off backfill script
  distributes every currently-open lead across active Telecallers at
  deploy time, so no one's queue goes empty on day one; (3) the VA
  Recovery queue's whole-team top tables stay unscoped (a distinct,
  already-documented funnel-health oversight view, not "my lead list") —
  only the main Lead Generation list (and the dashboard tile/attention
  strip that reuse its query) becomes per-telecaller scoped.
  New `leads.telecaller_id` column (nullable FK → users, mirrors
  `owner_id`'s shape exactly), `Lead::telecaller()` relation, a Telecaller
  dropdown on the Lead form (alongside Owner) and a matching list-filter
  dropdown next to the existing Owner filter. `DashboardMetrics::
  telecallerStats()`'s `new_leads` tile changed from a whole-system count
  to `visibleTo($user)`-scoped, so it can never disagree with what
  clicking through to Lead Generation actually shows. The newly-assigned
  telecaller gets the same `NewLeadNotification` an owner does.
  **Real self-inflicted test bug caught while writing tests, not shipped**:
  several new tests initially created a "genuinely unowned" or
  "unassigned-to-any-telecaller" lead fixture *after* an eligible Sales/
  Telecaller user already existed in the test — since `LeadObserver`'s
  round-robin claims literally every new lead with a null owner/telecaller
  the moment one exists, this silently gave the fixture a real owner/
  telecaller instead of leaving it null, making the assertion pass for the
  wrong reason (or, in one case — `GlobalSearchTest` — fail outright).
  Fixed by creating that fixture *before* any eligible user exists in each
  affected test, not by changing app behavior — worth remembering as a
  general gotcha for any future test needing a genuinely-unassigned Lead
  fixture in this codebase.
  Full suite 2897 passed (up from 2885), same 2 pre-existing unrelated
  flakes (`MeetingRequestTest` IST-window, `ManagementReportsTest`
  attendance-%). Pint clean. Verified against real local MySQL (not just
  SQLite) via a transaction-wrapped smoke script — created throwaway
  Sales/Telecaller users and leads, confirmed the exact Kiran/Mohit
  scenario is fixed and the telecaller round-robin assigns correctly,
  then rolled back, leaving no trace. Docs updated: `admin.md` (role
  table, role-mapping table, Section 16b's Needs-Attention-strip
  description + a new "Telecaller assignment" paragraph), `sales.md`
  (Section 1's summary-cards description), `telecaller.md` (the dashboard
  tile and Lead Generation section, both previously describing the old
  shared-queue premise).
  **Same-day correction, caught during deploy, not before**:
  `resolveLeastLoadedTelecaller()`'s primary-role-only eligibility (as
  described above, mirroring Sales) turned out to be silently non-
  functional the moment it hit production — a real data check
  (`User::where('role','telecaller')`) found **zero** users with
  Telecaller as a PRIMARY role at all; the only 2 real people doing
  telecaller work (Neha More, primary Accounts; Rohit Dhulasavant,
  primary Intern) both hold it as an ADDITIONAL role only. A primary-
  role-only round-robin would have matched nobody, forever, making the
  entire feature inert in practice despite passing every test (no test
  fixture happened to model "nobody has this as a primary role").
  Confirmed with the owner via AskUserQuestion: broadened
  `resolveLeastLoadedTelecaller()` to `User::withAnyRole(UserRole::
  Telecaller)` — a deliberate divergence from `resolveLeastLoadedSales()`'s
  own primary-role-only precedent (every real Sales rep genuinely has
  Sales as primary; Telecaller in practice never does), justified by
  actual production usage rather than blindly mirroring the Sales
  pattern. Now consistent with how Telecaller *visibility* already
  worked (`Lead::scopeVisibleTo()` already used `hasRole()`, not the bare
  primary-role column) — routing and visibility now agree. Rewrote the
  test that had asserted the old (wrong) behavior; full suite re-verified
  2898 green (same 2 pre-existing flakes), Pint clean, pushed directly to
  master post-deploy (no new PR — a same-session correction to code that
  had only just merged, not yet acted on by anyone).
- **2026-09-08 — Best Time to Call: per-lead recommendation is facts +
  global band, deliberately not a per-lead statistical model.** Owner
  reported leads not picking up and asked for the CRM to recommend when to
  call, "based on the history of the call made to that lead." Checked the
  codebase first, not assumed greenfield: `CallTimingMetrics` (built
  earlier) already computes a real, trustworthy team-wide connect-rate-by-
  hour band (90-day window, min 15 calls/hour) and already powers a "best
  time to call" hint on the Log a Call form and a retry-time suggestion —
  the global half of this ask was already live. What was missing was
  personalization to one specific lead. Confirmed via AskUserQuestion that
  true per-lead statistics weren't worth building: a single lead typically
  gets only 1-5 logged attempts total — far short of even
  `CallTimingMetrics`' own documented reasoning for why it won't trust
  per-weekday buckets built from 27-104 team-wide calls. A from-scratch
  per-lead rate would almost always be noise dressed up as a finding.
  Instead, new `App\Services\LeadCallTimingAdvisor::recommendationFor()`
  combines two honest, always-valid signals: the lead's own raw attempt
  history shown verbatim (exact time + outcome, no invented confidence),
  and the global best-hour band for the next attempt — with any hour
  already tried twice against *this* lead with no Connected outcome
  excluded from the recommendation. If every globally-good hour has
  already failed for this lead, it says so explicitly (`hours_exhausted`)
  rather than silently repeating a suggestion that hasn't worked. Confirmed
  via AskUserQuestion to surface this in all three places a
  telecaller/sales rep would want it: the Lead show page (full panel above
  the Calls list — LeadController::show()), a compact "Try: …" badge on
  the Lead Generation list (LeadController::index(), one `bestHours()`
  query per page load, not per row), and the same badge appended to My
  Day's lead follow-up and call-follow-up items
  (MyDayService::worklist()) — all three share one `bestHours()` /
  `LeadCallTimingAdvisor` computation per request rather than re-querying
  per lead. Zero schema changes (pure aggregation over existing `CallLog`
  rows), so deployment is `view:clear`+`view:cache` only, same
  low-friction precedent as the Employee Activity Timeline. 12 new Pest
  tests (advisor logic + both render surfaces + two My Day badge cases);
  full suite 3143 green (only the one already-documented, unrelated
  `MeetingRequestTest` IST-window flake — see [[feedback-gotchas]]), Pint
  clean. Smoke-tested end-to-end against real local MySQL via curl
  (login → seed real CallLog rows for a real lead → confirmed the panel,
  the list badge, all rendered correctly → cleaned up the SMOKETEST
  data), not just Pest — local dev had zero pre-existing lead call
  history to exercise this against otherwise.
- **2026-09-08 (same day) — Best Time to Call gained a third signal: the
  lead's own capture time, for sources where that timestamp reflects the
  prospect's own action.** After the milestone above shipped, the owner
  asked whether the moment a lead was captured in the CRM had been
  considered as a timing signal — it hadn't. Real signal, but not a
  universal one: for `Website`/`Whatsapp`/`MetaAds`/`PhoneEnquiry`, the
  creation timestamp is the prospect's own action (filled a form,
  messaged, called in); for `ColdCall` (and `Referral`/`Other`), it just
  reflects when a staffer entered the record, which would be a misleading
  signal to trust. New `LeadSource::isProspectInitiated()` draws that
  line. For a lead with zero call attempts of its own — today's weakest
  case, previously just the generic team-wide band — the capture hour now
  takes over as the *sole* recommendation rather than being diluted into
  a wide multi-hour band, since it's a far more specific personal signal.
  Once the lead has real call history, it folds in as one more candidate
  hour alongside the global band, subject to the same already-failed-
  twice exclusion as every other hour. Existing tests all built leads via
  `Lead::factory()` with a random `LeadSource` (the factory's own
  default) — updated every pre-existing `LeadCallTimingAdvisorTest`/
  `MyDayTest` case that asserts exact recommended-hour text to pin
  `source: ColdCall`, so the new signal can't intermittently change an
  assertion depending on which source faker happened to roll; 5 new tests
  cover the capture-hour behavior itself. Full suite 3147 green (same one
  pre-existing `MeetingRequestTest` flake), Pint clean.
- **2026-09-08 (same day) — Real incident: `App\Actions\MergeLeads` never
  carried `whatsapp_conversation_id` from the merged-away duplicate onto
  the surviving lead, silently orphaning the real conversation link on
  every lead merge involving a WhatsApp-sourced record.** Owner reported
  (two screenshots) that a lead's WhatsApp conversation, clearly visible
  and active in wadesk.in, showed nothing on the matching CRM lead page.
  Root-caused via real production data, not guessed: this lead (#171) was
  the surviving primary of a 29 Aug merge; the duplicate (#157) held the
  real `whatsapp_conversation_id`, but `MergeLeads::handle()` only ever
  reassigned Notes/CallLogs/Meetings/VA-funnel data/Activity — never this
  column, since it's an internal webhook-matching key, not one of the
  caller-resolved `$fields` the merge UI exposes. After the merge, #157
  (soft-deleted) still held the real conversation id, invisible to the
  webhook's `Lead::where('whatsapp_conversation_id', ...)` lookup (default
  scope excludes trashed); #171 was left with none at all. Separately
  root-caused why the pre-merge history (Aug 11-16 messages) was never
  captured in the first place, even before the merge: it predates the
  2026-08-14 wadesk.in-side fix that made wadesk notify the CRM on every
  message instead of just a conversation's first one — a wadesk-side gap,
  already fixed for new conversations, not retroactively recoverable.
  Fixed `MergeLeads::handle()` to carry the duplicate's
  `whatsapp_conversation_id` onto the primary whenever the primary doesn't
  already have one of its own — same "backfill only if unset" rule
  `WhatsappWebhookController::handleUnmatchedNumber()` already uses for
  this exact column. **Real gotcha caught before shipping, not in
  production**: `leads.whatsapp_conversation_id` is UNIQUE, and soft
  deleting the duplicate does NOT free that value (the column is still
  physically present on the trashed row) — writing the same value onto the
  primary first would throw a unique-constraint violation. Fixed by
  explicitly nulling the duplicate's own column before assigning it to the
  primary, both inside the same transaction.
  **Audited every merge this action has ever performed** (30 total,
  found via its own breadcrumb-note text) for the same orphaning pattern —
  found **9 currently-affected leads** (#113, #169, #171, #178, #180,
  #182, #195, #95, #285), not just the one reported. One of them (#285)
  had been the target of 3 separate merges over time, 2 of which had a
  real `whatsapp_conversation_id` to offer — resolved by checking which
  duplicate's Activity `created` event and note history actually showed
  real WhatsApp traffic (#280, the first/earlier merge) versus which had
  none at all (#281, a later, quieter merge), rather than picking either
  arbitrarily. Backfilled all 9 directly against production (same
  null-the-duplicate-first transaction as the code fix), verified after
  writing. Full suite 3149 green (same one pre-existing
  `MeetingRequestTest` flake), Pint clean, 2 new Pest tests (backfill-when-
  unset, don't-overwrite-an-existing-value — including the unique-
  constraint collision case).
- **2026-09-08 (same day) — Real incident: a Next Action popup kept
  prompting "log the call" for a lead everyone had already marked Lost.**
  Owner reported it via screenshot. Root cause: `CallLog.follow_up_at`
  going past due is the only condition any of four separate call sites
  ever checked — none of them looked at whether the underlying Lead had
  since moved to a terminal status. Same gap, four places:
  `CallFollowUpDueSource` (the reported Next Action banner),
  `SendCallFollowUpReminders` (the one-time notification command — meaning
  the underlying notification itself was already firing for a dead lead,
  not just the recurring banner), `MyDayService::worklist()`'s call-
  follow-up item, and `DashboardMetrics::telecallerStats()`'s
  `followups_due` tile. Fixed once, centrally: new
  `CallLog::scopeFollowUpDue()` bundles the existing null/date check with
  excluding a Lead callable whose `status` is Lost (a Customer callable —
  no equivalent terminal status — passes through unfiltered); all four
  call sites now use the scope instead of repeating the query inline, so
  this can't drift back out of sync across them again. **Real bug caught
  by the existing pre-change test for `DashboardMetrics::telecallerStats()`,
  not shipped**: the first version excluded via
  `whereNot('callable_type', Lead::class)`, which SQL evaluates to NULL —
  never true — for a CallLog with no callable at all
  (`callable_type IS NULL`), silently dropping every callable-less
  follow-up from every one of the four call sites. Fixed by adding an
  explicit `whereNull('callable_type')` branch alongside it. Deliberately
  scoped to Lost only, not also Converted — a converted lead's follow-up
  loop has moved to the Deal, which is a real, separate potential gap, but
  wasn't what was reported and wasn't touched here. 6 new/updated Pest
  tests across `CallFollowUpDueSourceTest`, `DashboardMetricsTest`,
  `MyDayTest`, and a new `SendCallFollowUpRemindersTest` (this command had
  zero prior test coverage). Full suite 3155 green (same one pre-existing
  `MeetingRequestTest` flake), Pint clean.
- **2026-09-08 (same day) — Daily lead-volume trend, by Sales Rep and by
  Telecaller, added to the Lead Source Performance report.** Owner asked
  how to see how many leads came in on which day; no such view existed —
  the Lead Generation list has no day-level tally, and the existing report
  only ever grouped by source/campaign for a whole month at a time. New
  `App\Services\LeadVolumeMetrics` (its own service class, not stacked
  onto `ReportMetrics`, per that file's own note that new reports
  shouldn't keep landing there) computes two day-by-day tables for the
  report's existing month range: one broken down by owner (Sales rep),
  one by telecaller, each with a per-person column built from whoever
  actually owns/is telecaller-assigned to a lead in that range — not a
  fixed active-roster snapshot, so a rep who left mid-range still shows
  their real historical numbers instead of silently folding into
  "Unassigned." An "Unassigned" column is appended last, only when at
  least one lead in range genuinely has none. Both tables + a totals row
  ship in the existing Export CSV too.
  **Real bug caught while writing tests, not shipped**: this feature's
  own first cut built per-person test fixtures with `owner_id => null`
  *after* Sales/Telecaller users already existed in the test, which
  `LeadObserver`'s round-robin auto-assign silently claimed — the exact
  `[[feedback-gotchas]]` gotcha (create a genuinely-unassigned fixture
  before any eligible user exists). Fixed by reordering the test
  fixtures, not the app.
  10 new Pest tests (`LeadVolumeMetricsTest` + 2 in
  `ManagementReportsTest`), full suite 3161 green (same one pre-existing
  `MeetingRequestTest` flake), Pint clean. Local dev MySQL has no Lead
  rows in any recent month, so the render smoke-test only confirmed a
  correct empty state (200, right headers, zero rows) — the real
  non-empty numbers were verified read-only against production data
  after deploy instead. `docs/user-guides/manager.md` updated (only
  `manager.pdf` regenerated — this report is Admin/Manager-only, no other
  guide references its content).
- **2026-09-08 (same day) — Closure-guidance Phase 1+2: a rep-set
  `stall_reason` tag on Lead/Deal, plus a Next Action banner source that
  brings a stalling one back up by name.** Owner asked how the CRM could
  guide Sales/Telecaller toward closure "on the move," not just report on
  it. Proposed a 4-phase plan grounded directly in the same-day VA Funnel
  diagnosis (every one of that report's 9 leads had real call history —
  the gap wasn't follow-up volume, it was six nameable objections that
  took an hour of manual reading to surface) and confirmed scope via
  AskUserQuestion before writing code: tag applies to both Lead and Deal
  (not Lead-only — the VA data showed real stalling on both sides of
  conversion), the missing-follow-up-date nudge is a soft prompt (not a
  hard block — sometimes there genuinely isn't a next step yet), and
  Phase 1+2 ship together (Phase 2's banner source is worthless without
  Phase 1's data).
  New `App\Enums\StallReason` (Budget/Competitor/Trust/Confused/
  AwaitingDecision — a bounded registry, not free text, so it stays
  aggregatable later) plus `leads.stall_reason`/`deals.stall_reason`
  columns. Tagged via a one-click dropdown (`x-stall-reason-picker`,
  plain onchange-submit, no Alpine needed) on the Lead/Deal's own page
  (Deal: hidden once Won/Lost — nothing to stall on once closed), and
  optionally from the Log a Call form itself when a Lead is preselected
  (mirrors the existing `<livewire:call-brief>` gating condition) —
  deliberately **never** clears an existing tag when left blank there,
  only when the dedicated picker's own "— Not stalling —" option is
  explicitly chosen, so an incidental field on a different form can't
  silently erase deliberate state. The same call form also gained a soft,
  non-blocking amber nudge when a Connected outcome has no follow-up date
  — the #1 pattern the VA report found ("I'll check it and let you
  know" → 3 unanswered calls).
  New `App\Services\NextAction\ObjectionFollowUpDueSource` (Phase 2),
  registered in `NextActionEngine::SOURCES` right after
  `CallFollowUpDueSource` — same no-role-gate reasoning (a Lead's
  owner_id OR telecaller_id both count; a Deal's owner_id only, Deals
  being Sales/Manager territory) and deliberately outranking every
  role's own "call a fresh lead" source below it: a real conversation
  that's stalling is a hotter use of the next few minutes than cold
  volume nobody's spoken to yet, which is the whole thesis the VA
  diagnosis was built on. Picks the single most-stale candidate across
  both Leads and Deals (3+ days since the last CallLog/Note touch),
  names the objection directly in the banner title instead of a generic
  "follow up" nudge.
  **Real bug caught by the new tests themselves before shipping**:
  `Lead::query()->get()->map()` into plain arrays is still an
  `Eloquent\Collection`, whose own
  `merge()`/`sortBy()` assume model items (`$item->getKey()`) — calling
  `->merge()` on the Lead and Deal candidate arrays threw
  "Call to a member function getKey() on array" until each candidate
  method explicitly downgraded to a base `Support\Collection` via
  `->pipe(fn ($mapped) => collect($mapped->all()))`.
  22 new Pest tests (`ObjectionFollowUpDueSourceTest` + `StallReasonTest`
  covering both controllers, both pickers, the call-form wiring, and the
  soft nudge), full suite 3183 green (same one pre-existing
  `MeetingRequestTest` flake), Pint clean, migrated + smoke-tested
  end-to-end against real local MySQL (tagged a real lead, backdated a
  real call, confirmed `NextActionEngine::nextFor()` actually surfaced
  the new prompt with the right title/body/link, not just that pages
  rendered without erroring). Docs updated: `sales.md`, `telecaller.md`.
  Phases 3 (a generalized "stalling" recovery view + reporting rollup)
  and 4 (an on-demand AI "suggest how to move this forward" button) are
  deliberately not built yet — see [[backlog]].
- **2026-09-08 (same day) — Closure-guidance Phase 3+4: a team-wide
  Stalling page, plus proactive AI check-ins that name the objection.**
  Owner confirmed "Now Phase 3 and Phase 4" right after Phase 1+2 shipped.
  New `App\Services\StallReasonMetrics` (`stallingLeads()`/
  `stallingDeals()`/`all()`/`countsByReason()`, each scoped by owner_id OR
  telecaller_id for a Lead, owner_id for a Deal, or unscoped for the whole
  team) hit the exact same `Eloquent\Collection::map()`-into-plain-arrays
  bug as `ObjectionFollowUpDueSource` did in Phase 2 — the same
  `->pipe(fn ($mapped) => collect($mapped->all()))` fix applied again,
  worth noting as a real recurring gotcha in this codebase, not a
  one-off. New **Stalling** page (`stalling.index`, `App\Http\Controllers\
  StallingController`, no dedicated Policy class — same
  menu.access-middleware-only convention as Client Radar/Festivals) shows
  a rep's own tagged leads/deals, most-stale-first; Admin/Manager
  additionally see the whole team's plus a count-by-reason breakdown. New
  sidebar item under the existing Sales Pipeline group, visible to
  Manager/Sales/Telecaller.
  **Real bug found mid-build, not shipped in Phase 1+2**: tagging a
  Lead/Deal with `stall_reason` fires a normal `updated` Activity log
  entry via the `LogsActivity` trait, and since `lastTouchedAt()` (which
  both `ObjectionFollowUpDueSource` and the new proactive-drafting job
  below read to decide staleness) includes `activities()->max
  ('created_at')`, the act of tagging something as stalling was itself
  read as "just touched" — silently resetting its own staleness clock and
  making the whole closure-guidance mechanism inert the moment a rep used
  it. Fixed with a new `$activityExcept = ['stall_reason']` on `Deal`
  (didn't have this property before — created fresh) and added to
  `Lead`'s existing one — `LogsActivity`'s `updated` handler already skips
  logging entirely once all changed fields are excluded, so a
  stall_reason-only edit now logs nothing, matching how `ai_score`/etc.
  were already excluded on Lead for the same reason.
  **Test-writing rediscovered an existing, already-documented gotcha
  rather than a new bug**: `LogsActivity`'s `created` hook always inserts
  its Activity row at real wall-clock "now," ignoring any backdated
  `created_at` passed to the parent model — a test fixture needs both
  backdated to simulate a genuinely old, quiet record. This exact
  behavior was already captured in `DraftDealStallFollowUpsCommandTest`'s
  own `backdatedDeal()` helper (with its own comment pointing at
  [[feedback-gotchas]]), found only after independently hitting and
  re-diagnosing the identical symptom while writing this phase's own
  tests — a reminder to grep for prior art in sibling test files before
  re-solving a fixture problem from scratch.
  **Phase 4 reframed after discovering existing infrastructure**: rather
  than building a new on-demand "suggest next move" button (the original
  plan), found `App\Jobs\DraftDealStallFollowUp` /
  `App\Console\Commands\DraftDealStallFollowUps` /
  `App\Notifications\DealStallFollowUpDrafted` already shipped in an
  earlier milestone — a scheduled command that finds quiet open deals and
  has AI draft a staff-only check-in note automatically. This is a
  strictly better match for the owner's "on the move" framing (a draft
  waiting for you beats a button you have to remember to click), so Phase
  4 became: (1) make the existing Deal job objection-aware — when
  `stall_reason` is tagged, `AiAssistant::draftDealStallFollowUp()`'s
  prompt names it directly and the system prompt switches to
  objection-specific guidance (Budget → offer a staged/installment plan,
  Trust → offer a reference or case study, Confused → offer a call) — and
  (2) build the missing Lead-side counterpart from scratch: new
  `App\Jobs\DraftLeadStallFollowUp` / `App\Console\Commands\
  DraftLeadStallFollowUps` (`app:draft-lead-stall-followups`, scheduled
  daily 10:40 IST, right after the existing deal job) /
  `App\Notifications\LeadStallFollowUpDrafted` / `AiAssistant::
  draftLeadStallFollowUp()`, mirroring the Deal versions field-for-field.
  **Deliberate divergence from the Deal command, documented in the new
  command's own docblock**: `DraftDealStallFollowUps` fires for *any*
  quiet open deal, tagged or not; `DraftLeadStallFollowUps` only fires for
  a Lead already tagged with `stall_reason` — justified by Leads being far
  higher volume than Deals (an untagged-quiet-lead-drafting job at Lead
  scale would be much noisier) and by keeping the mechanism's promise
  consistent app-wide ("tag it, and the system helps you — don't tag it,
  nothing fires automatically"). The pre-existing Deal command's
  unconditional trigger was deliberately left as-is rather than
  retrofitted to be tag-gated too, since that would be a behavior change
  to already-shipped automation outside this milestone's scope.
  32 new/updated Pest tests (`StallReasonMetricsTest`,
  `StallingControllerTest`, `DraftLeadStallFollowUpJobTest`,
  `DraftLeadStallFollowUpsCommandTest`, plus one new assertion on the
  existing `DraftDealStallFollowUpJobTest` confirming the tagged reason
  actually reaches the AI prompt), full suite green, Pint clean. Migrated
  against local MySQL (no new schema — Phase 1+2's two migrations already
  covered `stall_reason` on both tables), `MenuItemsSeeder` re-seeded
  locally for the new sidebar item, smoke-tested end-to-end via curl
  (throwaway SMOKETEST Sales + Manager users, a tagged-and-backdated
  lead — confirmed the Sales view shows the lead with no team section,
  the Manager view shows both the lead and the team/reason-breakdown
  sections, then deleted all three), and `schedule:list` confirmed the
  new `app:draft-lead-stall-followups` entry registered correctly.
  Docs: `sales.md`/`telecaller.md` extended (the existing "Stalling on:"
  bullet now also covers the new Stalling page and the objection-aware
  AI check-in), `manager.md` got a new "Stalling" section for the
  team-wide view; `sales.pdf`/`telecaller.pdf`/`manager.pdf` regenerated,
  the other unaffected handouts discarded per the established
  PDF-isn't-byte-stable gotcha.
- **2026-09-08 (same day) — Lead goal capture + Website/GBP link fields,
  driven by the real Meta Ads "What is your biggest goal?" form question.**
  Owner described training telecallers to ask a lead's goal (from the Meta
  form's 4 options) and, depending on the answer, either capture their
  Website/GBP link or hand them to a Sales Expert — and wanted the same
  thing to happen on wadesk. Investigated first, not assumed: this exact
  question already exists on the real ad form (`what_is_your_biggest_goal`,
  confirmed via a pre-existing test fixture using the real slugified answer
  `grow_my_business`) but had zero structured handling — it fell into
  `ImportMetaLead`'s generic "Additional form answers" note dump like any
  other unmapped custom question, and there was no Website/GBP field on a
  Lead at all (that only exists on a Customer, post-conversion, via
  `client_service_links`). Confirmed 4 scope decisions via AskUserQuestion
  before building: **goal** is a field any telecaller/sales rep can set on
  any lead regardless of source (not Meta-only), auto-prefilled when a Meta
  form answer matches; **budget** reuses the existing `estimated_value`
  field rather than a new one, since that's already the canonical number
  driving pipeline/incentive reporting; the "Not Sure" branch reuses the
  existing **Create Meeting** button already on a lead's page rather than a
  new "book with Sales Expert" mechanism; and **wadesk is deliberately
  deferred** — that's a separate repo/app whose AI assistant currently only
  answers FAQs and asks one generic after-hours discovery question, so
  teaching it this same goal-question/branching logic is its own follow-up
  build once the CRM-side workflow is proven, not bundled into this one.
  New `App\Enums\LeadGoal` (GenerateLeads/RankHigher/GrowBusiness/NotSure,
  `needsWebsiteOrGbp()` true for the first three) plus nullable
  `leads.goal`/`website_url`/`gbp_url` columns. `ImportMetaLead::
  matchGoal()` mirrors `matchServiceId()`/`matchBudget()`'s existing
  best-effort-parsing shape, normalizing the answer (lowercase, punctuation
  to spaces) to match either the option's display text ("Grow My Business
  Online") or a slugified value ("grow_my_business") — both variants are
  real, since Meta's actual behavior here depends on how the advertiser
  built the form. **Real bug caught by the new tests, not shipped**: the
  first version matched on the answer's VALUE alone across every custom
  question (matchServiceId()'s approach), which false-positived — an
  unrelated "Not sure yet" answer to a completely different question (which
  service they want) was being read as the NotSure goal, since "not sure"
  is common free text, unlike a specific service name. Fixed by requiring
  the field's KEY to mention "goal" first (matchBudget()'s own restriction,
  applied here for the same reason).
  Capture happens in two places, mirroring `stall_reason`'s own established
  UI pattern exactly: an inline "🎯 What are they looking for?" panel on
  the lead's own page (new `LeadController::updateGoalCapture()`, a real
  edit form — blank explicitly clears, unlike the incidental-field case
  below) and the same fields folded into the Log a Call form when a lead is
  selected (`CallLogController::store()`, same "only writes what the rep
  actually filled in, never clears on a blank incidental field" guard
  `stall_reason` already uses there). Two next-step banners on the lead's
  own page, driven purely by `goal` + whether a link is already captured:
  an indigo "🌐 Ask for their Website or GBP link" note once the goal needs
  one and neither is set yet, or a purple "🎓 They want expert advice" note
  linking to the page's own Create Meeting section (`leads/show.blade.php`
  gained `id="meetings"` for the anchor) when NotSure. Deliberately NOT
  added to `Lead::$activityExcept` (unlike `stall_reason`) — capturing a
  real goal/link is genuine evidence of contact, so it should refresh
  `lastTouchedAt()`, not be excluded from it.
  30 new/updated Pest tests (`LeadGoalCaptureTest` + `ImportMetaLeadJobTest`
  extended, including a rewrite of the one pre-existing test that used the
  real `what_is_your_biggest_goal`/`grow_my_business` fixture, since that
  answer is now correctly extracted instead of landing in a note), full
  suite green, Pint clean, migrated against local MySQL (no seeder/menu
  changes — this reuses existing Lead/Call routes and pages, no new
  sidebar item). Docs: `sales.md`/`telecaller.md` extended (new "Their
  goal, and their Website/GBP link" bullet in both, plus a one-line update
  to the existing Meta Ads leads paragraph in `sales.md`).
- **2026-09-08 (later same day) — CRM-side half of the wadesk.in goal-question
  port: extended `/api/leads/context`, new `/api/leads/goal-capture`, and a
  shared "needs Sales" notification.** Owner asked for the same lead-goal
  flow (2026-09-08's earlier entry) to also run on WhatsApp via wadesk.in's
  after-hours AI assistant, so a lead who only ever messages — never gets a
  call — still goes through goal → Website/GBP-or-Sales-handoff. Researched
  wadesk.in's actual codebase before designing anything (a separate Next.js/
  Prisma repo this session has no deploy access to): confirmed it has **no**
  conversation-state tracking (every AI reply re-infers context from message
  history, stateless), **no** structured/JSON output from Claude (replies are
  freeform text only), **no** interactive WhatsApp message sender (only plain
  text), and **no** "notify Sales" mechanism of any kind — a real port, not
  a copy-paste. Confirmed 4 scope decisions via AskUserQuestion: after-hours
  only (reuse the existing AI-live trigger, don't build a new always-on
  one); real tappable WhatsApp list buttons over plain numbered text (a new
  `sendInteractiveMessage()` needed on the wadesk.in side); a real "needs
  Sales" signal, not just a reply; and the owner runs wadesk.in's own
  deploy commands, since this session has no SSH access to that VPS.
  This entry is the CRM-side half only, self-contained and inert until
  wadesk.in actually calls it (same "ship the receiving side first" pattern
  as the 2026-08-14 WhatsApp-conversation-capture milestone).
  `LeadContextController::show()` (already called by wadesk.in before every
  after-hours reply) now also returns `goal`/`website_url`/`gbp_url` plus a
  precomputed `needs_link` boolean (`LeadGoal::needsWebsiteOrGbp()`) — sent
  precomputed rather than making wadesk.in's TypeScript re-implement that
  enum's branching. New `POST /api/leads/goal-capture` (same
  `VerifyWhatsappWebhookToken` Bearer-token auth as every other wadesk.in↔CRM
  route) is the write-back: partial update, only the fields actually sent
  are touched (mirrors `ImportMetaLead`'s existing "only write what you
  have" discipline), looks the Lead up via the same `findOpenByPhone()`
  every other wadesk.in-facing endpoint already uses.
  New `App\Notifications\LeadWantsExpertAdviceNotification`, fired from
  `LeadObserver::updated()` whenever `goal` transitions to NotSure —
  deliberately fires from **either** write path (this new API endpoint OR
  the telecaller UI's existing goal-capture panel/Log-a-Call form) through
  one shared observer hook, rather than building two divergent "needs
  Sales" mechanisms for the same field. Notifies the lead's owner and
  telecaller (whichever are set) — `wasChanged('goal')` alone guards
  against re-notifying on every later unrelated save to an already-NotSure
  lead.
  22 new Pest tests (`LeadContextTest` extended — context response fields,
  goal-capture write/partial-update/validation/404, and the shared
  notification firing exactly once per real transition), full suite green,
  Pint clean. Docs: new `integrations.md` "Integration 14," and fixed a
  stale drift caught along the way — `README.md`'s guide table still said
  "10 automated workflows" (a pre-existing, unrelated undercount; the file
  was already at 13 sections before this entry's own addition).
  The wadesk.in-side build (interactive list sender, the one new
  `Conversation` state column, and the assistant's own branching logic) is
  a separate follow-up — see the next entry once that's built.
- **2026-09-09 — wadesk.in-side goal-question flow shipped and deployed
  (wadesk.in PR #2), plus a real, separate production incident found and
  fixed during that deploy.** New `src/lib/goal-flow.ts` in the wadesk.in
  repo: the after-hours assistant now sends a real WhatsApp interactive
  list (new `sendInteractiveMessage`-style `sendInteractiveListMessage()`
  in `meta.ts`) asking the CRM's own "biggest goal" question to any lead
  the CRM recognises as an open, un-asked Lead, matches the tapped
  option's row `id` (now also extracted in `api/webhook/route.ts`
  alongside the existing `.title` extraction), and branches to asking for
  a Website/GBP link or telling them a Sales Expert will follow up —
  writing both back via new `src/lib/crm-goal-capture.ts`
  (`POST /api/leads/goal-capture`, this repo's own new endpoint from the
  entry above). Deliberately **no Anthropic call anywhere in this flow**
  — deterministic id-matching plus a plain URL regex, kept reliable and
  free. New `Conversation.aiFlowPending` column (wadesk.in's own schema)
  is the only new persisted state; the captured values live on the CRM's
  Lead, re-fetched fresh each turn.
  **Real incident found while deploying wadesk.in's side**: its
  `docker-compose.yml` had never listed most of the app's actual required
  env vars under the `app` service's `environment:` block —
  `ANTHROPIC_API_KEY`, every `CRM_*` var, `WADESK_SERVICE_KEY`, and all
  three `VAPID_*` push vars were correctly set in the VPS's `.env` but
  silently never reached the running container (Docker Compose only
  injects what's explicitly named in `environment:`, regardless of what
  `.env` defines). Confirmed via `docker compose exec app env` returning
  nothing for any of them. Every affected function fails silently by
  design (same "AI failure never breaks the core workflow" convention
  this whole ecosystem uses), so this had zero visible symptoms —
  meaning the after-hours AI assistant had likely never sent a real reply,
  nothing had reached the CRM's WhatsApp timeline, web push to agents had
  been silently failing, and the CRM's own server-to-server calls into
  wadesk.in may have been rejected, for however long that file had been in
  this shape. Fixed by listing every required var explicitly (pushed
  directly to wadesk.in's `master`, no PR — matching that repo's own
  overwhelming direct-to-master convention). Owner ran the full redeploy
  (has no SSH access from this session) and confirmed together, step by
  step: migration applied clean, all 10 previously-missing vars now
  present via `docker compose exec app env`, container healthy with no
  startup errors. See [[feedback-gotchas]] (CRM-side memory) for the full
  reusable gotcha. Real on-device WhatsApp test (does the interactive list
  actually render/tap correctly) is the one thing left unverified — needs
  a real test message after hours. Docs: `sales.md`/`telecaller.md`
  extended, `integrations.md`'s Integration 14 gained a troubleshooting
  note about the docker-compose gotcha.
- **2026-09-09 (same day) — Automatic + manual WhatsApp engagement for
  Meta Ads leads (App\Jobs\SendLeadWelcomeMessageJob /
  App\Jobs\SendLeadCheckInJob).** Owner wanted a way to open WhatsApp's
  24-hour session window on a Meta Ads lead who's never messaged the
  business, so staff (or the after-hours assistant) can message freely
  instead of being limited to approved templates. Corrected a premise
  before building: an unsolicited "thanks for filling the form" first
  message can't be a Utility-category template (Meta's own policy reserves
  Utility for updates on an existing transaction/interaction) — this app
  already learned that the hard way (`visibility_audit_first_invite_
  template_name`'s own comment explicitly documents choosing Marketing for
  exactly this shape of message). Confirmed 2 decisions via
  AskUserQuestion: build both an automatic welcome (fires once, at
  creation) and a manual re-engagement button (for a lead who's since gone
  quiet) rather than just one; and refined the welcome copy per the
  owner's own framing — not just "thanks," but a specific, easy-to-answer
  question ("what's a good time to call?") deliberately chosen over a
  generic "any questions?" close, since a concrete question gets more
  replies, and a reply is what actually opens the window.
  `SendLeadWelcomeMessageJob` mirrors `SendVisibilityAuditFirstInviteJob`'s
  shape closely (same wadesk.in `/api/send-template` contract, same
  `hasStaffWhatsappReplySince()` guard, same "ships inert until the
  template is Meta-approved and configured" pattern) but is deliberately
  scoped to skip a GMB-tagged lead, which already gets the VA-specific
  first invite instead — `LeadObserver` dispatches exactly one of the two,
  never both, checked in both `created()` and the same `meta_leadgen_id`/
  `service_id` backfill branch in `updated()` the VA invite already uses
  for the same WhatsApp-race reason. `SendLeadCheckInJob` mirrors
  `SendQuotationWhatsAppJob`'s shape instead (manually triggered, no
  idempotency guard in the job itself, re-sendable) — the new **📱 Send
  WhatsApp check-in** button on a lead's own page
  (`LeadController::sendCheckIn()`) applies a soft 24-hour cooldown via
  the new `Lead.last_checkin_sent_at` column so a considerate reminder
  stays possible without risking an accidental repeat blast to the same
  contact. New `Lead.welcome_message_sent_at` is the automatic job's own
  idempotency guard. Neither job logs to `VisibilityAuditTouch` (that
  table is VA-specific) — each instead leaves a plain internal Note on
  success, for staff visibility in the lead's own timeline.
  Both new templates are Marketing category with the same required
  "Stop promotions" opt-out button, two body variables (name, tagged
  service or "your enquiry" as a fallback) — see `config/services.php`'s
  `lead_welcome_template_name`/`lead_checkin_template_name` comments for
  the exact suggested body text handed to the owner to submit to Meta for
  approval. 36 new Pest tests (`SendLeadWelcomeMessageJobTest`,
  `SendLeadCheckInJobTest`, `LeadCheckInTest`), full suite green, Pint
  clean, migrated against local MySQL (two new nullable `leads` columns,
  no seeder/menu changes). Docs: `sales.md`/`telecaller.md` extended,
  new `integrations.md` "Integration 15."
