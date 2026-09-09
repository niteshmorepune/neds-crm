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
Older entries (2026-06-10 through 2026-08-25) moved to `docs/decisions-log-archive.md` to keep this file under the context size limit — same format, nothing summarized or dropped. Recent entries continue below.

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
- **2026-09-09 (same day) — Correction: both templates shipped bilingual
  (English + Hindi in one body), 4 variables not 2, both Meta-approved and
  live.** The entry above described the templates as first drafted
  (2 variables, English only) — the owner asked for both languages in the
  same message (not separate template variants) so any lead can read it
  regardless of language, and for `lead_checkin` specifically, real line
  breaks rather than one continuous paragraph. WhatsApp Manager's own
  template editor auto-increments `{{` on every insertion and won't let an
  earlier number be reused, so the Hindi half repeats the same name/service
  as `{{3}}`/`{{4}}` rather than reusing `{{1}}`/`{{2}}` — accepted as the
  simplest fix rather than fighting the editor. `SendLeadWelcomeMessageJob`/
  `SendLeadCheckInJob`'s `variables` payload updated from
  `[name, service]` to `[name, service, name, service]` to match. Both
  templates submitted via browser automation in WhatsApp Manager (owner's
  own already-authenticated Chrome session, explicit permission each step)
  under the correct WABA — a real mismatch was caught and corrected first
  (the WABA matching wadesk.in's own config had a completely different,
  unrelated phone number than the real Marketing number, 9112095202).
  PR #179 merged + deployed same day once Meta approved both: `migrate
  --force` (the two new `leads` columns), prod `.env` gained
  `WADESK_LEAD_WELCOME_TEMPLATE_NAME=lead_welcome`/
  `WADESK_LEAD_CHECKIN_TEMPLATE_NAME=lead_checkin`, `config:cache`,
  verified resolving correctly over SSH.
- **2026-09-09 (same day) — Automatic re-engagement + visibility parity for
  non-GMB Meta leads, closing a real gap the welcome/check-in launch left
  open.** Once welcome/check-in shipped, comparing it against the existing
  GMB Visibility Audit funnel exposed an asymmetry: a GMB-tagged lead who
  goes quiet gets an automatic funnel-stall recovery nudge
  (`SendVisibilityAuditRecoveryNudges`, 2-4hr thresholds), but every OTHER
  Meta lead depended entirely on a telecaller remembering to click
  **📱 Send WhatsApp check-in** by hand. Confirmed the fix via
  AskUserQuestion: build both an automatic nudge (mirroring the GMB
  pattern) AND a Lead Generation visibility badge, not just one; the owner
  picked a flat **6-hour** no-reply wait (their own "4-6 hours" range,
  picked the midpoint) over the GMB thresholds' shorter funnel-stage
  timers, since this is a simpler "did anyone reply at all" check, not a
  funnel-progression one.
  New `App\Console\Commands\SendLeadWelcomeFollowUps`
  (`app:send-lead-welcome-followups`, scheduled `everyThirtyMinutes()`,
  same cadence as the VA recovery nudges) reuses the existing
  `SendLeadCheckInJob` directly rather than a new job — it's the same
  template/send either way, only the trigger differs (staff click vs.
  elapsed time). Fires **once** per lead (`last_checkin_sent_at IS NULL`
  guards it, the same column the manual button's own 24h cooldown already
  uses, so an automatic and a manual send can never double up).
  Deliberately excludes GMB-tagged leads with zero extra code — a GMB lead
  never gets `welcome_message_sent_at` set in the first place (it gets the
  VA invite instead), so the `whereNotNull('welcome_message_sent_at')`
  filter already excludes them for free.
  **Real bug caught and fixed before this could ship, not a new one**:
  `Lead::outreachAttemptSummary()`'s existing `whatsapp_inbound_reply`
  detection (any note with `user_id=null` and no `[Sent via WhatsApp by`
  prefix) already had a documented, deliberately-accepted gap for a rare
  case (a Meta intake note with no prefix either). But
  `SendLeadWelcomeMessageJob`/`SendLeadCheckInJob`'s own confirmation notes
  ("✨ Automated welcome message sent..." / "✨ Re-engagement check-in
  sent...", both `user_id=null`, no WhatsApp-outbound prefix) hit this
  exact same gap on **every single successful send** — not a rare edge
  case here, but guaranteed, which would have made the new
  `isAwaitingWelcomeReply()` check read "already replied" the instant the
  welcome note itself was created, silently disabling the entire feature
  before it could ever fire. Fixed with a new
  `Lead::INTERNAL_MARKER_NOTE_PREFIXES` exclusion list in
  `outreachAttemptSummary()` (checked before either the inbound or
  outbound classification), scoped narrowly to just these two known marker
  bodies — the older, rarer intake-note gap was left as-is, already
  documented as an accepted false-negative elsewhere. This same fix also
  correctly restores `isUnresponsive()`'s own accuracy for any Meta lead
  that received the automatic welcome, which had been silently
  broken since the welcome/check-in feature shipped earlier the same day
  (a lead could rack up 3+ unanswered attempts and still never surface as
  unresponsive, since its own welcome-confirmation note was misread as a
  reply) — caught proactively while building this feature, not from a bug
  report.
  New `Lead::isAwaitingWelcomeReply()` (no time threshold — pure "still
  quiet" signal, reused by both the command and) `Lead::
  isOverdueForWelcomeReply()` (narrowed to the 6h threshold — drives the
  Lead Generation per-row badge and the Needs Attention strip count).
  Deliberately does NOT exclude a lead whose one-shot auto check-in
  already fired from the badge/count — "still worth a phone call" is a
  different question from "still eligible for another automated WhatsApp
  send," and only the command itself needs that second guard. New
  `attention=welcome_no_reply` link on the Needs Attention strip (teal, a
  color not already used by the other five), mirroring
  `unresponsive`/`stale_status`'s existing PHP-filter-then-`whereIn`
  pattern exactly. 20 new Pest tests
  (`SendLeadWelcomeFollowUpsTest`, `LeadWelcomeReplyTest`, plus 2 new cases
  in `LeadListPrioritizationTest`, including one asserting the
  `isUnresponsive()` fix directly), full suite green, Pint clean. Docs:
  `sales.md`/`telecaller.md` extended.
