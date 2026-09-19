# NEDS CRM — Decisions Log Archive

This archives older entries from CLAUDE.md's "Decisions log" section, moved out on 2026-09-09 (and again on 2026-09-17) to keep CLAUDE.md under the auto-loaded context size limit. Entries here are in the same format and chronological order as before — nothing was summarized or dropped, only relocated.

See `CLAUDE.md` for the current spec and the most recent decisions (2026-09-16 onward).

## Decisions log (archived: 2026-06-10 through 2026-08-25)

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

## Decisions log (archived: 2026-09-03 through 2026-09-13)

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
  updated (see below for the two follow-on entries from the same investigation).
- **2026-09-10 — Real incident: Meta's own "healthy ecosystem engagement"
  pacing throttle (error 131049) was silently blocking some `lead_welcome`
  sends, with nothing to ever retry them — fixed + deployed (`153f226`).**
  Surfaced from an owner-shared wadesk.in inbox screenshot showing a
  failed send to a real lead. `WadeskMessageStatusController`'s existing
  failure-detection (shipped hours earlier, `d97fee3`) correctly resets
  `welcome_message_sent_at`/`wadesk_id` on a Meta-rejected send, but
  nothing downstream ever re-attempted it — a throttled lead just sat
  with no automation scheduled to reach it again. Checking real
  production data found this wasn't a one-off: of the last 9 automated
  welcome sends, the most recent 2 leads in a row had failed 100% of
  their attempts — a real shift, not random noise. New
  `App\Console\Commands\RetryFailedLeadWelcomeMessages`
  (`app:retry-failed-lead-welcome-messages`, every 30 min) re-dispatches
  `SendLeadWelcomeMessageJob` for a lead with a real prior failure note
  (not a never-attempted one — that stays `LeadObserver`'s job), waits
  `Lead::WELCOME_RETRY_WAIT_HOURS` (2h) between attempts, and gives up
  after `WELCOME_RETRY_MAX_ATTEMPTS` (3) with a one-time note pointing
  staff at the manual "Send WhatsApp check-in" button rather than
  hammering a live throttle forever. Reuses existing `Lead` notes as the
  source of truth for attempt history (no new columns). 10 new Pest
  tests, full suite green, Pint clean, deployed via the standard SSH
  `git pull` (no migration), verified live against the real leads that
  surfaced the gap.
- **2026-09-10 (later) — Synced answered wadesk.in WhatsApp voice calls
  into the CRM's own `CallLog`, so they count toward employee performance
  reports like a manually-logged phone call.** Owner asked for this once
  wadesk.in's new inbound WhatsApp Calling API feature (a same-day
  build in that separate repo — see its own CLAUDE.md) was confirmed
  working end-to-end. Confirmed scope via AskUserQuestion first:
  **answered calls only** — `call_logs.user_id` is required (not
  nullable), and only a call someone actually answered has an
  unambiguous person to attribute it to; a missed/declined/failed call
  has no clear "who" and was left visible only in wadesk's own thread/
  `Call` table, not synced here. New `App\Http\Controllers\Api\
  WadeskCallLogController` (`POST /api/webhooks/wadesk/call-log`, same
  Bearer-token trust boundary as every other wadesk.in-facing endpoint)
  matches the caller's phone to a Customer or Lead (Customer checked
  first, same precedence `WhatsappWebhookController` already used) and
  the answering wadesk agent's email to a real CRM `User.email` — a
  wadesk agent whose login doesn't share an email with a real CRM user
  (e.g. a test/admin account) silently doesn't get logged, same
  "never guess an attribution" contract every other wadesk↔CRM bridge
  already follows. New nullable+unique `call_logs.wadesk_call_id` column
  is the dedup key (mirrors the `leads.welcome_message_wadesk_id`/
  `checkin_wadesk_id` external-reference-id pattern) so a retried
  delivery can't double-log the same call.
  **Real refactor along the way, not a new bug**: `WhatsappWebhookController
  ::findCustomer()`'s phone-matching logic (Customer.phone/
  alternate_phone, then an individual Contact's phone) was private to
  that controller — extracted to a new `Customer::findByPhone()` static
  method (mirroring `Lead::findOpenByPhone()`'s own shape exactly) once
  this second real call site needed the identical resolution, so the two
  webhook endpoints can't drift apart on how a client's phone number is
  matched. Deliberately kept this integration minimal — it does NOT
  replicate `CallLogController::store()`'s other side effects (lead
  promotion, AI re-scoring, Visibility Audit touch logging, stall-reason/
  goal capture) for an auto-synced call; those all depend on a human
  actually filling out notes/outcome/next-action, which a WhatsApp call
  sync has no equivalent of. 8 new Pest tests
  (`WadeskCallLogTest`), full suite green, Pint clean, migrated locally
  (one new nullable/unique column, no seeder/menu changes).
  `sales.md`/`telecaller.md` extended.
- **2026-09-12 — Meta Ads recommendation + offer funnel: 3 new entry
  offers, a 16-cell recommendation matrix, and an in-app Razorpay Orders
  checkout deliberately separate from the GBP offer's existing hosted
  Payment Pages.** Owner asked for a full lead-recommendation-and-offer
  funnel for Meta Ads leads (a much bigger ask than the existing GBP-only
  Visibility Audit funnel): Meta's ad form asks a "biggest goal" question
  (already captured as `Lead.goal` since 2026-09-08) and a "monthly
  budget" question (not previously captured as a structured field), and
  the CRM should recommend one of 4 entry offers based on the combination,
  show a personalized page, and take payment. Inspected the existing
  Visibility Audit funnel thoroughly before building anything (its offer
  controller, funnel-tracking redirect hops, Razorpay Payment Page
  checkout, webhook, purchase-matching job) — real, working, production
  infrastructure, not a prototype — and reused as much of its shape as
  made sense without touching any of its own code or behavior.
  **New `App\Enums\LeadBudgetRange`** (`under_3000`/`3000_6000`/
  `6000_12000`/`12000_plus`, plus a `leads.budget_range` column) — a new
  bounded field, not a bucketing of the pre-existing `estimated_value`
  (money paise) or `ai_budget_band` (AI-inferred Low/Medium/High) columns,
  both of which mean something different. Mirrors `LeadGoal`'s own
  precedent (added as its own field 2026-09-08) exactly, including a new
  `matchBudgetRange()` in `ImportMetaLead` alongside the pre-existing
  `matchGoal()`/`matchBudget()` — matched on the *count/size of numbers
  present* (a range answer carries 2, a boundary answer carries 1) rather
  than phrase-matching raw text, since neither "₹" nor "," nor "+"
  reliably survive Meta's own slugified answer variant. Capturable
  manually too, right next to the existing Goal picker on the lead page
  and the Log a Call form (same "only writes what's actually filled in"
  guard `stall_reason`/`goal` already use there).
  **`App\Support\OfferRecommendationMatrix`** is the single source of
  truth for all 16 (goal x budget) cells — recommendation name, offer,
  price, and the exact Hindi/English headline/positioning/explanation
  copy the owner specified — a flat literal array, not generated, so a
  future price or copy change is a one-line edit in one place, never
  duplicated into a view. Deliberately keeps two messaging rules baked
  into every cell's explanation: a small budget is never framed as "too
  low" (framed instead as "start with the right diagnostic step"), and a
  large budget is never framed as "you have money to spend" (framed as a
  genuine strategic planning opportunity) — both directly from the
  owner's own spec.
  **`App\Enums\OfferKey`** (GbpAudit/LeadGenerationAudit/
  WebsiteGrowthAudit/GrowthStrategy) is the single source of truth for
  price/route/CTA — `GbpAudit::price()` returns ₹120 but
  `usesInAppCheckout()` is false for it alone, since that offer
  deliberately keeps its own pre-existing Payment Page checkout untouched
  (its route, price, and business logic were explicit "do not touch"
  constraints). The 3 new offers get a real, new in-app Razorpay Orders +
  Checkout.js flow (`OfferCheckoutController`, new `offer_purchases`
  table) instead of the GBP page's hosted-Payment-Page mechanism —
  confirmed via AskUserQuestion, since both were legitimate "reuse
  existing architecture" choices already live in this app (the GBP
  page's own Payment Pages, and `QuotationAdvancePaymentController`'s
  in-app Orders API flow for milestone billing). Picked the in-app flow:
  it needs zero manual Razorpay Dashboard setup before any of the 3 new
  offers can take real money (the GBP page's Payment Pages, by contrast,
  are a real "ships inert until the owner manually configures a URL per
  tier" gap, same class as several past WhatsApp-template integrations),
  and price is server-side by construction — `OfferCheckoutController::
  order()` always resolves the amount from `OfferKey`, never the request,
  and `verify()` re-fetches the order from Razorpay directly rather than
  trusting anything the browser reports back, exactly mirroring
  `QuotationAdvancePaymentController`'s own signature-verification
  pattern. The existing async `RazorpayWebhookController` (previously
  invoice-only) now also branches on `notes.offer_key` as a backup path
  alongside the synchronous `verify()` call — same belt-and-suspenders
  precedent as the invoice/quotation flow already has.
  **Personalized recommendation page** at `/offers/recommendation/{token}`
  — confirmed via AskUserQuestion that this needed an unguessable
  `recommendation_token` (`Str::uuid()`, lazily generated exactly like
  `Quotation::public_token`/`VisibilityAuditPurchase::report_token`)
  rather than the GBP page's own bare `?lead=123` convention: that
  existing pattern only ever tags a later funnel event, it never renders
  anything about the lead back on screen, while this new page genuinely
  displays a real name/goal/budget — a guessable sequential id would let
  someone enumerate other leads' info. `noindex, nofollow` on the page
  itself plus a `robots.txt` disallow. Recommendation generation
  (`App\Actions\GenerateLeadRecommendation`) is idempotent — re-visiting
  the page, or re-saving the same goal/budget from the lead page, never
  creates a duplicate token or re-fires a `recommendation_created` funnel
  event unless the underlying cell actually changed. The dev/QA
  `?goal=&budget=` test-mode route (`/offers/recommendation/test`,
  registered before the `{token}` route so it's never swallowed as one)
  hard-404s via `abort_if(app()->environment('production'), ...)` — real
  users can never manipulate their own recommendation or price through a
  query parameter.
  **New `offer_funnel_events` table** (mirrors
  `visibility_audit_funnel_events`' shape, generalized across all 4
  offers) captures `recommendation_created`/`recommendation_viewed`/
  `offer_viewed`/`offer_cta_clicked`/`payment_started`/
  `payment_succeeded`/`payment_failed` — deliberately did NOT build a new
  full team-wide funnel dashboard alongside this (the existing Visibility
  Audit Funnel dashboard is its own, separate, already-large feature) —
  the events are captured and queryable, and the immediate admin/sales
  need (understanding one specific lead's recommendation/offer/payment
  state) is served directly on the Lead detail page's new **📋
  Recommendation & Offer** panel instead. A team-wide rollup dashboard
  for this new funnel is a reasonable fast-follow, not built here.
  **3 new offer pages** (`/offers/lead-generation-audit`,
  `/offers/website-growth-audit`, `/offers/growth-strategy`) visually
  match the pre-existing GBP page's own hand-rolled CSS custom-property
  design system almost exactly (same `--navy`/`--blue`/`--blue2` etc.
  variable names, same section shapes — hero, problem cards, audit grid,
  offer/buybox, steps, trust stats, FAQ, sticky mobile CTA) via a new
  shared `resources/views/offers/partials/styles.blade.php` include —
  the GBP page's own file was deliberately left completely untouched
  (not even refactored to use the new shared partial) rather than risk
  any visible/behavioral change to a page explicitly called out as
  off-limits. Alpine.js loaded via CDN on these 3 new pages specifically
  (not the app's own Vite bundle) — pulling in `resources/js/app.js`
  would also load Livewire and Tailwind's CSS reset, which these
  self-contained public pages don't use and don't want fighting the
  hand-tuned stylesheet; the recommendation page needs no Alpine at all
  (its CTAs are plain links to an offer page), so it only includes the
  CSS half of that partial. Growth Strategy's "Where should your next
  ₹10,000 go?" section explicitly states it's a strategic example, not a
  guaranteed allocation or return, per the owner's own explicit
  instruction not to imply any guarantee. Every proof point used
  (66+ Maharashtra businesses / 12+ years / 4.9 Google rating) is one of
  the 3 already-approved claims elsewhere in this app — nothing new was
  invented.
  **Real bug caught while testing, not shipped**: `OfferRecommendationMatrix::
  all()` initially used `Collection::flatMap()` to flatten the 4x4 cell
  array — since every goal's inner array shares the same 4 budget-value
  string keys, `flatMap()`'s internal collapse silently overwrote all but
  the last goal's 4 cells, leaving only 4 of 16 recommendations reachable
  from `all()` (the `for()` lookup used by every real request was
  unaffected — this only broke the coverage-check helper). Fixed with a
  plain nested-foreach flatten instead of relying on Collection semantics
  here.
  134 new Pest tests (all 16 matrix combinations x price/offer/headline/
  CTA, checkout order/verify price-tampering/signature/offer-mismatch/
  IDOR/idempotency cases, all 4 offer pages render, the admin panel, the
  budget-range Meta-import parser, goal-capture roundtrip), full suite
  otherwise green (9 unrelated pre-existing date/UTC-midnight-boundary
  flakes hit across `DailyPrioritiesDigestTest`/`WeeklyOwnerDigestTest`/
  `MeetingRequestTest`/`DraftProjectDailyUpdatesCommandTest`/
  `SendProjectUpdatesDigestTest`/`DailyReportTest` — none of these files
  or the code they test were touched this session; confirmed via `git
  log` that they predate this work). Pint clean, `npm run build` run (new
  Tailwind utility classes on the Lead page's new panel). Migrated and
  smoke-tested end-to-end against real local MySQL: a throwaway
  `SMOKETEST` lead created directly, its real recommendation URL hit live
  (correct offer/price/noindex confirmed), its offer-page view and
  funnel-event rows verified in the database, then force-deleted — no
  real Razorpay key configured locally, so the checkout endpoints
  themselves were only exercised through the Pest suite, not live
  payment. `sales.md`/`telecaller.md` extended (their PDFs regenerated;
  the other 8 handouts' regenerated-but-unchanged PDF bytes discarded per
  the established PDF-isn't-byte-stable gotcha), `BUILD_PLAN.md` gained a
  new Milestone 12 section. **Remaining manual step**: none required to
  ship — the 3 new offers use the in-app Razorpay Orders flow with the
  same `RAZORPAY_KEY_ID`/`RAZORPAY_KEY_SECRET` already live in production
  for invoice/quotation payments, so no new Razorpay Dashboard
  configuration is needed before this goes live (unlike the GBP page's
  own Payment Pages).
- **2026-09-12 (later same day) — Milestone 15: GBP offer moved to the
  same in-app Razorpay Orders + Checkout.js flow as the other 3 offers,
  reversing Milestone 12's own "out of scope to change" call on this
  page's payment flow.** The owner asked directly: *"In-app Orders +
  Checkout.js to be implemented, so we do not need to use razorpay
  payment page anymore."* Milestone 12 (above) had deliberately kept
  `GbpAudit::usesInAppCheckout()` false and left this page's own external
  Payment Page checkout untouched, on the reasoning that it was a
  separate, already-working mechanism not worth touching in that
  milestone — the owner has now explicitly asked for exactly that change,
  so this entry supersedes that "out of scope" note rather than leaving it
  standing as if still true.
  When asked how the GBP profile link would be collected (the old
  Payment Page had its own custom form field for it), the owner corrected
  the premise: *"If the lead had already submitted the lead form with
  his/her information then why to again ask for it. GBP link is only the
  information actually to collect if he/she wants to pay Rs120, correct?"*
  — so the new flow prefills name/phone/email straight from the matched
  Lead into Checkout.js and asks the visitor for only the one genuinely
  new piece of information, the GBP/Maps link.
  Confirmed 2 scope decisions via AskUserQuestion before building: (1)
  **GBP tier only** — the Website (₹240) and "Both" (₹360) tiers exist in
  code/config but aren't linked from any live page today (confirmed by
  re-reading `VisibilityAuditOfferController`'s own pre-existing
  docblock), so they're left fully untouched, including their own external
  Payment Page + the shared `RazorpayVisibilityAuditWebhookController`;
  and (2) **remove** the now-dead `RAZORPAY_PAYMENT_PAGE_GBP_AUDIT` env
  var/config key specifically, not the webhook controller itself, which
  still needs to keep serving those two dormant tiers if ever re-linked —
  verified safe by reading that controller first: it matches a completed
  payment to a tier by **amount**, never by which Payment Page config key
  was used, so removing only this one key can't affect Website/Both.
  New `App\Http\Controllers\VisibilityAuditCheckoutController`
  (`order()`/`verify()`), mirroring `OfferCheckoutController`'s own
  price-resolved-server-side/re-fetch-order-at-verify pattern almost
  exactly, but hardcoding the ₹120 GBP price and requiring a `gbp_url`.
  Unlike `OfferPurchase`, `VisibilityAuditPurchase` has never had a
  pending/paid status concept (every row already IS a completed payment),
  so there's no placeholder row to create at `order()` time — the tier,
  submitted `gbp_url`, and matched lead id all ride in the Razorpay
  order's own `notes` field instead, read back authoritatively via
  `fetchOrder()` at `verify()` time rather than trusted from the browser.
  New `RazorpayClient::fetchPayment()` — Checkout.js's success handler
  never returns the contact/email a payer typed into Razorpay's own hosted
  modal (only shown when no `prefill` exists, i.e. an unmatched/anonymous
  visitor), so without this, `RecordVisibilityAuditPurchase`'s own
  `Lead::findOpenByPhone()` matching would have no phone number to work
  with for that visitor. A new VA-specific checkout script partial
  (`offers/partials/visibility-audit-checkout-script.blade.php`) is a
  deliberate small duplication of the shared `checkout-script.blade.php`
  used by the other 3 offers, not a generalization of it — the shared
  script's `order()` call sends no request body at all, while this one
  needs to send `{gbp_url}` and block the whole payment flow client-side
  until that field is filled.
  Traced through `VisibilityAuditRecoveryNudgeEmail`'s and the WhatsApp
  recovery template's existing links (both still point at the old
  `.checkout?tier=gbp` route) before concluding no change was needed
  there: `VisibilityAuditFunnelTrackingController::checkout()` already has
  a graceful "config not set → redirect to the landing page instead of a
  raw external redirect" fallback, which now fires automatically for GBP
  once its config key is gone — an old recovery link still works, it just
  lands the recipient on the offer page instead of directly into a
  payment flow, an accepted, expected consequence of an in-app
  JS-triggered checkout (which, unlike an external Payment Page URL,
  can't be deep-linked into "already paying").
  14 new Pest tests (`VisibilityAuditCheckoutTest` — price always ₹120
  server-side regardless of client input, signature/amount/tier tampering
  all rejected, idempotent on `razorpay_payment_id`, lead-matched prefill
  vs. anonymous-payment-entity fallback both covered),
  `VisibilityAuditOfferControllerTest` rewritten for the new
  `$razorpayConfigured` gate (replacing the old `$gbpPaymentUrl`-based
  assertions), full suite green (no new unrelated flakes beyond the
  already-documented ones), Pint clean. `VisibilityAuditFunnelTrackingTest`
  needed no changes — it sets `services.razorpay.payment_pages.gbp_audit`
  directly at runtime, which still works as a pure-unit exercise of that
  controller's own tier-agnostic redirect logic even though the config
  file no longer defines that key by default.
- **2026-09-12 (later same day) — Unified the Meta Ads funnel: the
  goal+budget recommendation matrix now decides the offer for EVERY Meta
  lead, GMB included, instead of the GBP path being routed separately by
  campaign/service tag.** Owner correction, not a bug report: after
  Milestone 12 shipped, the owner pointed out that "GMB Visibility" was
  never really its own separate thing — the underlying ask was always
  "Online Visibility," and the lead form's goal/budget questions exist
  precisely so the CRM can pick the best-fit offer itself, before handing
  off to Sales. Checked the actual routing code before agreeing: `LeadObserver::
  sendVisibilityAuditInviteIfEligible()` decided purely from
  `service_id === gmbServiceId()` — a tag applied at import/campaign
  time — and never once consulted the lead's own `goal`/`budget_range`
  answers, even though `OfferRecommendationMatrix` (Milestone 12) already
  existed specifically to make that exact decision for the other 3
  offers. A GMB-tagged lead was hard-locked into the GBP offer by campaign
  tag alone, even if their real answers pointed at Growth Strategy;
  conversely a non-GMB-tagged lead whose answers resolved to GbpAudit
  already worked correctly at the recommendation-page/matrix level (the
  matrix's own `OfferKey::GbpAudit::url()` already pointed at the existing
  `/offers/visibility-audit` page) — the gap was specifically in the
  automated first-touch MESSAGING layer, which never even ran the matrix
  for a GMB-tagged lead.
  Confirmed 2 scope decisions via AskUserQuestion before touching
  anything (both revenue-critical, GBP checkout explicitly out of bounds
  to modify per this file's own Milestone 12 entry): (1) the matrix
  becomes authoritative for every Meta lead's first-touch message,
  service-tag routing demoted to a fallback for when goal/budget haven't
  been captured/parsed yet (not retired outright — a real, still-live
  case: some Meta ad campaigns' own forms never ask the "biggest goal"
  question at all, so `goal`/`budget_range` can legitimately stay null
  forever for a real GMB lead); (2) the recovery-nudge cron unifies into
  one command across all 4 offers rather than two independent ones.
  **`App\Actions\GenerateLeadRecommendation::handle()`** is now the single
  dispatch point for the whole funnel's first-touch message, not just a
  data-resolution action — right where it already logs a
  `RecommendationCreated` event (only on a genuine new/changed
  recommendation, its pre-existing `$dirty` guard), it now also dispatches,
  gated on `meta_leadgen_id !== null` (never auto-messages a lead whose
  goal/budget a rep filled in by hand on a Website/Referral lead): `offerKey
  === GbpAudit` → the existing, byte-for-byte unchanged
  `SendVisibilityAuditFirstInviteJob`/`-EmailJob`; any other offer → new
  `App\Jobs\SendOfferRecommendationReadyJob` (modeled closely on the VA
  job's own shape — same wadesk.in contract, same `hasStaffWhatsappReplySince()`
  guard, same skipped/opted-out handling — but logs a plain internal Note
  on success instead of a `VisibilityAuditTouch` row, since that table is
  VA-specific; mirrors `SendLeadWelcomeMessageJob`'s note-logging pattern
  instead). Its Dynamic-URL button points straight at
  `/offers/recommendation/{token}` — no new tracking redirect hop needed,
  since `OfferRecommendationController::show()` already logs
  `RecommendationViewed` on every hit. New `Lead.recommendation_notified_at`
  column is its idempotency guard, mirroring `visibility_audit_invited_at`
  exactly.
  **`LeadObserver`**: `sendVisibilityAuditInviteIfEligible()` and
  `sendWelcomeMessageIfEligible()` collapsed into one
  `routeMetaLeadFirstTouch()` — calls `GenerateLeadRecommendation::handle()`
  first; only when it returns null (goal/budget not yet known) does the
  observer fall back to the previous service-tag behavior verbatim (GMB tag
  → VA invite, else → generic `SendLeadWelcomeMessageJob`), so a GMB
  campaign lead whose form never asked the goal/budget questions still
  reliably gets the GBP invite exactly as before. Both call sites
  (`created()`, and the `updated()` race-condition branch for Meta's own
  auto-WhatsApp-beats-the-webhook case) updated to call the one method.
  **Recovery nudges**: new `App\Services\OfferFunnelMetrics`
  (`pendingRecommendationNudges()`/`pendingOfferNudges()`, modeled on
  `VisibilityAuditFunnelMetrics`'s own `pendingLandingNudges()`/
  `pendingCheckoutNudges()` shape, explicitly excluding any
  GbpAudit-recommended lead — that offer stays entirely on the existing,
  untouched VA metrics/nudge pipeline) + new
  `App\Jobs\SendOfferRecoveryNudgeJob` (mirrors
  `SendVisibilityAuditRecoveryNudgeJob` exactly: per-event `nudged_at`
  marking via new `offer_funnel_events.nudged_at` column, same
  re-check-before-send purchase/staff-reply guards) + new
  `App\Console\Commands\SendOfferFunnelRecoveryNudges`
  (`app:send-offer-funnel-recovery-nudges`, every 30 min), which inlines
  the old `SendVisibilityAuditRecoveryNudges` command's own body unchanged
  for the GBP path and adds the new `OfferFunnelMetrics`-driven path for
  the other 3 — one cron, one combined dispatch count, replacing the old
  GBP-only command (deleted; its one command-level test in
  `VisibilityAuditRecoveryNudgeTest.php` updated to call the new command
  name, every other test in that file — job/metrics behavior — left
  untouched since none of it changed). Same 2h/4h wait-threshold pacing
  as the original VA nudges, applied identically to the new "offer"
  (hotter)/"recommendation" (softer) stages.
  **Real bug caught by the new tests, not shipped**: `OfferFunnelEvent`'s
  `$fillable` array never included `nudged_at` at all (a gap from
  Milestone 12, since nothing had needed to write that column until this
  session added the mark-nudged logic) — every `$event->update(['nudged_at'
  => now()])`/`OfferFunnelEvent::create([..., 'nudged_at' => ...])` call
  silently no-op'd on that one field, so an event could never actually be
  marked nudged and a test creating an already-nudged fixture never
  produced one either. Fixed by adding it to `$fillable`, caught
  immediately by 3 new tests failing for the right reason before this
  shipped.
  **Corrected an assumption made mid-build, not shipped as stated**: the
  build brief assumed `CallLogController::store()`'s existing goal-capture
  block already called `GenerateLeadRecommendation` (a third "choke point"
  alongside the Lead page and Meta import) — checked the actual code and
  found it does not: that form only ever writes `goal` (never
  `budget_range`, and the form has no budget_range field at all) and
  never invokes the action, so logging a call today cannot trigger a
  recommendation or a first-touch message. Left as-is rather than silently
  expanding scope to add a new budget field + UI to the Log a Call form —
  a real, separate gap, tracked in the backlog rather than bundled into
  this routing-unification change.
  **Second real bug, caught by the full suite (not the new tests) before
  push**: `RetryFailedLeadWelcomeMessagesTest.php`'s own fixtures never
  seed a GMB `Service` row, so `VisibilityAuditFunnelMetrics::gmbServiceId()`
  returned null inside that file specifically. The OLD
  `sendWelcomeMessageIfEligible()`'s bare `$lead->service_id !==
  gmbServiceId()` check happened to treat "both null" as equal (not
  eligible), so a null-service lead's welcome dispatch was — by accident,
  not by design — silently skipped at creation in that one file's test
  environment, which is what let those tests pass without ever re-faking
  the queue after building their fixtures. The new
  `routeMetaLeadFirstTouch()`'s explicit `$lead->service_id !== null &&`
  guard closes that accidental null-equals-null match — correct and
  identical to old behavior in production (a real GMB Service always
  exists, so `gmbServiceId()` is never actually null there) — but it
  meant this one file's fixtures started actually dispatching
  `SendLeadWelcomeMessageJob` at `metaLead()` creation time, same as they
  always should have, breaking 6 of that file's `assertNotPushed`
  assertions (they were unknowingly asserting against the retry command's
  dispatch AND a leftover creation-time one). Fixed the test, not the
  routing: added a fresh `Queue::fake()` right before each test's own
  `Artisan::call()`, same established pattern
  `VisibilityAuditFirstInviteTest`'s own sweep-command test already uses
  for exactly this reason ("re-fake to discard the creation-time
  dispatches from LeadObserver").
  30 new Pest tests (`GenerateLeadRecommendationTest`,
  `LeadFirstTouchRoutingTest`, `SendOfferRecommendationReadyJobTest`,
  `SendOfferRecoveryNudgeJobTest`, `OfferFunnelMetricsTest`,
  `SendOfferFunnelRecoveryNudgesCommandTest`), full suite green — 3462
  tests, same one pre-existing unrelated `MeetingRequestTest` IST-window
  flake documented in the Milestone 12 entry above (confirmed via `git
  log` that file predates this session) — Pint clean. No menu/
  sidebar changes (this is pure backend routing — same buttons, same
  pages staff already use), so no re-seed needed on deploy; two small
  migrations (`leads.recommendation_notified_at`,
  `offer_funnel_events.nudged_at`). `.env.example` documents the 3 new
  `WADESK_OFFER_RECOMMENDATION*`/`WADESK_OFFER_RECOVERY_TEMPLATE_NAME`
  vars. WhatsApp template submission for the 3 new templates is a
  separate follow-up step with the owner (same process as every prior
  template), not done in this session.
- **2026-09-12 (later same day) — Milestone 14: team-wide Offer Funnel
  dashboard, folded into the existing VA dashboard page rather than a new
  sidebar entry.** The obvious next step once Milestone 13 unified the
  routing: the reporting was still split in two (VA's own dashboard for
  GBP, nothing at all for the other 3 offers — a gap the Milestone 12
  backlog note already flagged as a deliberate fast-follow). Confirmed 3
  scope decisions via AskUserQuestion before building: (1) a genuinely
  UNIFIED view across all 4 offers, not a 3-offer-only page living
  alongside the untouched VA one — reconciling `OfferPurchase` and
  `VisibilityAuditPurchase` (two separate tables, no shared parent) into
  one summary/trend/by-offer table; (2) GBP shown top-of-funnel only
  (recommended/notified/viewed/reached-offer/paid, same 5 columns as the
  other 3) — its own richer post-purchase pipeline (audit_ready →
  gmeet_held → report_sent) stays exclusively on the pre-existing
  GBP-detail section below, no duplication; (3) folded into the existing
  `reports/visibility-audit-funnel` page (same route, same
  `menu.access:visibility-audit-funnel` key) rather than a new sidebar
  entry — lower risk than it sounds, since nothing about the existing
  GBP-specific section's own controller logic or tests needed to change,
  only new sections added alongside it.
  `App\Services\OfferFunnelMetrics` gained `funnelSummary(?OfferKey, from,
  to)` (5-stage shape — recommended → notified → viewed → reached_offer →
  paid — deliberately matching `VisibilityAuditFunnelMetrics::
  funnelSummary()`'s own 5 keys one-for-one so a GBP row and a non-GBP row
  can share one table; this offer family's own "viewed the offer's own
  page" stage is folded into "recommended" for this shared shape, since
  GBP has no separate recommendation-page-vs-offer-page split to mirror —
  see the class's own docblock), `byOfferBreakdown()` (one row per non-GBP
  offer), `trend()` (daily recommended/paid, Asia/Kolkata-bucketed, same
  `Carbon\CarbonPeriod` gotcha as every other trend method in this app),
  `leadsForStage()` (drill-down), and `byGoalBudgetBreakdown()` (the one
  genuinely offer-agnostic method here — `Lead.goal`/`budget_range` are
  shared columns regardless of which offer ends up recommended, so this
  queries `Lead` directly rather than going through either offer-specific
  table; "paid" is a union of both `OfferPurchase` and
  `VisibilityAuditPurchase` lead ids, the one place the two tables are
  explicitly reconciled).
  `VisibilityAuditDashboardController::index()` now also injects
  `OfferFunnelMetrics`, builds the by-offer table (GBP's own row remapped
  from `VisibilityAuditFunnelMetrics::funnelSummary()` via a new
  `remapGbpSummary()`), sums all 4 rows into one "all offers" summary
  (`sumStages()` — recomputes stage-to-stage percentages from the summed
  counts rather than averaging per-offer percentages, which would be
  wrong at very different offer volumes), and merges the two trend series
  by array index (`combineTrends()` — both trend() methods already
  iterate the identical `CarbonPeriod` for the same `$from`/`$to`, so
  zipping by index is safe and avoids a second date-string re-match). New
  `offerLeads()` action + `reports/offer-funnel-leads.blade.php` view for
  the 3 non-GBP offers' own drill-down; GBP's cells in the new by-offer
  table link to the existing `leads()` action instead, via a small
  `$gbpStageMap` in the view (recommended→eligible, notified→invited,
  viewed→landing_viewed, reached_offer→checkout_viewed, paid→paid).
  Sidebar label renamed "VA Funnel Analytics" → "Offer Funnel Analytics"
  (menu **key** and route name deliberately left unchanged — only the
  seeder's `label` field and the page's own title/H1 changed) to match
  the page's now-broader scope; `docs/user-guides/manager.md`,
  `admin.md`, and `integrations.md` updated for both the rename and the
  new "All offers" section (grepped every guide for the old label first,
  per this project's own "check every guide, not just the obvious one"
  gotcha).
  45 new/updated Pest tests (14 new `OfferFunnelMetricsTest` cases + 8 new
  `VisibilityAuditDashboardTest` cases, the existing 23 dashboard tests
  otherwise unchanged apart from one renamed-title assertion), full suite
  green, Pint clean. No new migrations — every column/table this reads
  already existed from Milestone 12/13. Menu re-seed needed on deploy
  (label change only, no new item/route).
- **2026-09-12 (later same day) — service_id auto-derived from the
  resolved recommendation offer, correcting a real data-quality gap the
  owner flagged: staff had been manually tagging almost every Meta
  lead's service as GMB by habit, regardless of what the lead actually
  wanted.** Owner's own question: *"how CRM will understand which
  service is the lead looking for on the basis of information filled on
  the lead form? As team is manually entering GMB for almost all
  leads."* Investigated before proposing anything: `ImportMetaLead::
  matchServiceId()` only auto-sets `service_id` if the ad form itself
  asks a "which service" question whose answer text exactly matches an
  active Service name — since the real ad forms only ask "biggest goal"
  and "budget" (the two questions the whole recommendation matrix is
  built on), this essentially never fires for a real Meta lead, so
  `service_id` stays null until a human sets it. Confirmed
  `GenerateLeadRecommendation::handle()` (Milestone 12/13) already
  decides the offer shown to a lead purely from `goal`+`budget_range`,
  fully independent of `service_id` — so the habitual GMB tagging wasn't
  corrupting the actual offer/routing, only Service-wise reporting and
  any service-keyed Lead Assignment Rules.
  Confirmed 2 scope decisions via AskUserQuestion: (1) auto-derive
  `service_id` from the resolved `recommendation_offer_key` rather than
  locking the field or leaving it a pure training/process fix — with an
  explicit mapping only for the 3 offers that have an unambiguous 1:1
  Service match (GbpAudit→GMB, WebsiteGrowthAudit→Website Design &
  Development, LeadGenerationAudit→Performance Marketing);
  GrowthStrategy spans multiple services and is deliberately left
  untouched rather than forcing a misleading tag; (2) backfill existing
  Meta leads too, not just going forward — the owner had already noticed
  the reporting distortion, so a going-forward-only fix would have left
  it uncorrected for months.
  `GenerateLeadRecommendation::handle()` now sets `service_id` (via new
  public `serviceIdForOffer()`) inside the same `if ($lead->
  recommendation_key !== ...)` branch that already guards every other
  recommendation-changed write — so it fires exactly when a lead's
  recommendation is first generated or later changes (e.g. goal/budget
  edited), never on an unchanged re-check, and deliberately **overwrites**
  whatever `service_id` was there before (a genuinely new/changed
  recommendation is a stronger signal than a habitual guess). Once set,
  a later call with the SAME recommendation does not re-touch it — so a
  rep who manually corrects it afterward with real information isn't
  fought by the system (the "lock the field" alternative was explicitly
  not chosen). GMB is resolved via the same resilient
  `whereIn(['GMB', 'GMB Services'])` lookup `VisibilityAuditFunnelMetrics::
  gmbServiceId()` already uses, rather than a bare hardcoded name, since
  that Service row has been renamed in production before (see
  [[feedback-gotchas]]).
  New `App\Console\Commands\BackfillLeadServiceTags`
  (`app:backfill-lead-service-tags`, `--dry-run` supported) corrects
  every existing lead with a resolved `recommendation_offer_key` whose
  `service_id` disagrees with `serviceIdForOffer()` — reuses the exact
  same mapping method rather than duplicating it, same "one-off
  correction command, not a permanent scheduled job" pattern as
  `BackfillClientProvisioning`.
  18 new Pest tests (`GenerateLeadRecommendationTest` extended — all 3
  mapped offers set the right service, GrowthStrategy leaves it alone,
  overwrite-vs-no-re-touch-once-settled both covered, the renamed-Service
  resilience case; new `BackfillLeadServiceTagsTest`), full suite green,
  Pint clean. No migration (reuses the existing `leads.service_id`/
  `recommendation_offer_key` columns). The backfill command itself has
  not yet been run against production — that's a deploy-time step, same
  as any other one-off correction command in this app.
  **Found the actual root cause while fixing this, not just the
  symptom**: `sales.md`/`telecaller.md` themselves were still telling
  staff *"tagging it (Edit → Service → GMB) is what actually turns the
  automated invite on — it does nothing at all until that's set"* — true
  before the 2026-09-12 "unified funnel" milestone, false after it (goal+
  budget already decides and fires the invite on its own), but nobody had
  gone back to correct this specific instructional line when that
  milestone shipped. Staff were very likely following their own training
  material exactly as written. Fixed both guides (removed the now-false
  instruction, explained the auto-derive instead), plus
  `integrations.md`'s Integration 12 ("Only Meta Ads leads tagged the GMB
  service are invited" — same staleness) and Integration 15 ("except a
  GMB-tagged one" — same), and added a new Integration 16 documenting the
  unified goal+budget-decides-everything mechanism as its own entry,
  since none existed despite it being the single most consequential
  routing decision in the app. **Also found and fixed a second, closely
  related bug this same staleness was hiding**: `VisibilityAuditFunnelMetrics::
  awaitingServiceTag()`/`leadsAwaitingServiceTag()` (the "Your gaps"/
  manager-dashboard nag that told staff a lead needs a service tag) never
  checked whether the lead already had a resolved `recommendation_offer_key`
  — meaning every Growth Strategy-recommended lead (deliberately left
  service-less, no 1:1 Service match) would have nagged forever with no
  way to clear it except mistagging it, the exact same harmful workaround
  in a different guise. Both methods now also exclude any lead with a
  resolved recommendation. `manager.md`'s "Awaiting service tag" callout
  description updated to match. 2 new Pest tests for this second fix.
- **2026-09-12 (later same day) — Website Growth Audit checkout now
  collects the website URL, mirroring the GBP tier's own gbp_url capture
  — plus a matching fix to a gap that turned out to also exist on GBP.**
  Owner's own observation: *"Like the way we are capturing Google
  Business Profile / Maps link on visibility page before paying Rs 120,
  similarly i think website url to capture on
  .../offers/website-growth-audit and there links to be reflected on
  crm."* Investigated before building: `OfferPurchase` (the table behind
  all 3 non-GBP offers) had no `website_url` column at all, and the
  Website Growth Audit checkout sent no extra body — a genuine gap,
  exactly as the owner suspected. Also found two closely related gaps
  while checking how GBP's own gbp_url capture actually behaves end to
  end: (1) the GBP checkout field never prefilled from `Lead.gbp_url`
  even when a telecaller (or the wadesk after-hours assistant) had
  already captured it via the separate Goal-capture flow — it always
  asked fresh; (2) neither GBP's nor (the new) Website Growth Audit's
  checkout-captured link ever wrote back onto the Lead's own
  `gbp_url`/`website_url` fields — it only ever lived on the purchase
  row, invisible outside that one purchase's own detail. Confirmed 3
  scope decisions via AskUserQuestion before building: prefill +
  only-ask-if-blank (not always-ask-fresh), write the captured link back
  onto the Lead (not purchase-only), and fix GBP's own prefill gap in the
  same pass rather than leaving it for later.
  New `OfferKey::collectsWebsiteUrl()` (true only for
  `WebsiteGrowthAudit` — Lead Generation Audit and Growth Strategy have
  no single missing piece of information to ask for, so neither gets this
  field). `OfferCheckoutController::order()` now validates `website_url`
  as required only when the resolved offer's `collectsWebsiteUrl()` is
  true, carries it in the Razorpay order's own `notes`, and stores it on
  the new `offer_purchases.website_url` column (mirrors
  `visibility_audit_purchases.gbp_url`'s shape exactly). The shared
  `offers/partials/checkout-script.blade.php` gained an optional
  `websiteUrlFieldId` parameter — when a page passes one (only
  `website-growth-audit.blade.php` does), `pay()` blocks with an inline
  error and focuses the field before ever opening Checkout.js, same
  guard shape as the VA-specific script's own `gbpUrlValue()` check;
  omitted entirely for the other 2 offers, which send no extra body at
  all, byte-for-byte unchanged from before this field existed.
  `OfferPageController::render()` (shared across all 3 offer pages) now
  also passes `leadWebsiteUrl` to every view for simplicity, though only
  the Website Growth Audit template actually renders it — same harmless
  "pass it everywhere, only one page uses it" precedent
  `VisibilityAuditOfferController::show()` already established for its
  own new `leadGbpUrl`.
  **The two related GBP-side fixes, applied in the same pass**:
  `VisibilityAuditOfferController::show()` now fetches the full `Lead`
  model (previously only its id) and passes `leadGbpUrl` so the checkout
  field's own `value="{{ $leadGbpUrl ?? '' }}"` prefills correctly.
  `RecordOfferPurchase`/`RecordVisibilityAuditPurchase` (both jobs) now
  write the checkout-captured link back onto the matched Lead's own
  `website_url`/`gbp_url` field whenever one was captured — deliberately
  **overwrites** any existing stale value on the Lead (a value someone
  just deliberately typed at checkout is a stronger, fresher signal than
  whatever was there before), and is a true no-op (no query, no activity
  log entry) when the submitted value matches what was already
  there — the common case once prefill is working, since the payer
  usually just leaves the field as shown. Reused the exact same `update()`
  (not `saveQuietly()`) the 2026-09-08 Goal-capture milestone already
  chose for this same pair of fields, since capturing a real link is
  itself genuine evidence of contact and should refresh
  `lastTouchedAt()`, not be excluded from it.
  Also fixed, as a direct consequence of adding a required field to a
  page that previously had none: the hero, final-section, and sticky
  mobile-bar CTAs on `website-growth-audit.blade.php` previously
  dispatched the checkout event directly (there was nothing to fill in
  first) — now they scroll to the buybox and focus the `website_url`
  field instead, exactly mirroring the pattern already established on
  the GBP page for the same reason; only the buybox's own dedicated Pay
  button still dispatches checkout directly. The hero CTA also gained a
  `$razorpayConfigured` gate + "Coming soon" fallback it had never had
  (a small, pre-existing, unrelated gap fixed along the way since it sat
  directly in the code being touched).
  16 new/updated Pest tests (`OfferCheckoutTest` — website_url required
  only for Website Growth Audit, stored on the order/purchase, written
  back to the Lead and overwriting a stale value, untouched for the
  other 2 offers; `OfferPagesRenderTest` — the field renders/prefills
  correctly and is absent from the other 2 pages;
  `VisibilityAuditCheckoutTest` — GBP's own prefill and write-back/
  overwrite covered too; one pre-existing `OfferCheckoutTest` case
  switched from `WebsiteGrowthAudit` to `LeadGenerationAudit` as its
  example offer, since it predates this field and was never actually
  testing anything related to it), full suite green, Pint clean. New
  migration (`offer_purchases.website_url`, nullable), migrated locally.
  Docs: `sales.md`/`telecaller.md` extended with a short note on the new
  field's prefill/write-back behavior, right next to the existing
  Goal-capture section both guides already had.
- **2026-09-12 (later same day) — "Dynamic landing page" for the Meta ad's
  own thank-you-screen button: `/offers/find-my-recommendation`, a
  phone-lookup gateway in front of the existing per-lead recommendation
  page.** Owner asked which URL to actually give Meta as the funnel's "main
  landing page," floating a UTM-parameter idea. Researched Meta's real
  Instant Form capabilities before answering (web search + Meta/third-party
  docs — see this session's own sourced findings): Instant Forms do support
  dynamic macros (`{{ad.id}}`, `{{campaign.name}}`, etc.) via a form-level
  "Tracking Parameters" feature, but those are delivered **server-side**
  bundled into the lead's own webhook/API payload — never appended to the
  browser's own redirect URL — and even that mechanism only ever carries
  **ad-level** facts known before anyone submits, never the *individual
  submitter's* own identity (name, phone, lead id), since Meta doesn't hand
  that back to the browser at click time at all. Confirmed separately: the
  Thank-You screen's own "Website URL" button field has no dynamic
  parameter support of any kind — a single static URL, fixed once per
  form. This is a real, structural platform limitation, not a
  configuration gap on our side — no UTM scheme can carry "this is lead
  #4821, recommend them Growth Strategy" to a static button.
  Also found a real, live bug while investigating this:
  `VisibilityAuditFunnelTrackingController::enter()` — the route Meta's
  static button had been pointed at — is hardcoded to always redirect to
  the GBP ₹120 offer page regardless of the actual lead's own resolved
  recommendation, a leftover from before the funnel was unified across all
  4 offers (Milestone 13). Anyone clicking through today sees a ₹120 GBP
  audit even when their real WhatsApp message (sent moments later) is
  actually going to recommend the ₹999 Growth Strategy or another offer
  entirely.
  Since no URL-based mechanism can carry per-submission identity, built the
  only structurally possible "dynamic" landing page: the visitor confirms
  the phone number they just gave Meta, the CRM looks it up
  (`Lead::findOpenByPhone()`, the same matching every other wadesk.in/
  Meta-facing lookup already uses) and forwards them straight to their own
  already-resolved recommendation — `GenerateLeadRecommendation::handle()`,
  the same decision every other channel (WhatsApp, email) already relies
  on, so there is exactly one place in the app that ever decides "which
  offer," never a second parallel implementation.
  New `FindMyRecommendationController` (`show()`/`lookup()`) +
  `/offers/find-my-recommendation` (GET the form, POST the lookup — the
  POST throttled at `throttle:10,1`, stricter than a normal page view,
  since a phone number is not unguessable the way a `recommendation_token`
  is). A GBP-recommended lead is routed through the existing
  `offers.visibility-audit.enter` hop (so it still counts as a real
  `LandingViewed` event, same as every other channel that reaches GBP); a
  non-GBP lead is routed straight to `Lead::recommendationUrl()` — its
  `RecommendationViewed` event is already logged by
  `OfferRecommendationController::show()` itself, no separate hop needed.
  A lead not found yet, or found but not yet resolved (goal/budget still
  missing), gets a plain, friendly in-page message rather than an error —
  the first genuinely is possible (someone submits, then immediately
  visits before the webhook has even landed) and both are expected,
  non-error states.
  `docs/meta-ads-playbook.md`'s own "Thank-you screen" section updated
  with the real URL to give Meta and the reasoning above — and flagged
  (not rewritten) that the rest of that doc's own form design (a "Which
  service are you looking for?" multiple-choice question, ₹10,000+ budget
  bands) predates the current goal+budget-driven form entirely and is
  stale; a full rewrite against the live form is a separate follow-up, not
  done here.
  7 new Pest tests (`FindMyRecommendationTest` — renders, validates,
  not-found/not-yet-resolved friendly states, both redirect branches, the
  last-10-digit phone-matching fallback), full suite green, Pint clean. No
  migration (reuses existing Lead columns/matching). Smoke-tested
  end-to-end against real local MySQL (both the GBP and non-GBP redirect
  branches, cleaned up after).
- **2026-09-12 (later same day) — Real bug, owner-caught: the new
  `find-my-recommendation` page (and several other pages, once audited)
  showed the Support WhatsApp line instead of Marketing.** Owner spotted
  it live: `wa.me/918007733737` (Support) was showing where
  `wa.me/919112095202` (Marketing) belonged. Root cause: this session's
  own new pages read a single generic `company.whatsapp` config value —
  which turned out to be set to the Support number — instead of the two
  already-correctly-configured, separately-tracked business lines
  (`services.wadesk.support_number`/`marketing_number`, live since the
  2026-08-03 multi-number rollout). Audited every usage of
  `company.whatsapp` across the app, not just the one page reported —
  found 3 MORE pages with the identical mistake, none related to this
  session's own new work: the client portal's shared `whatsapp-button`
  component (used on `portal/home`, `portal/tickets/create`,
  `portal/tickets/index` — all Support-context, correctly needed
  `support_number`) and the pre-existing `recommendation-unavailable`
  fallback page (lead-facing, needed `marketing_number`). Fixed all 6
  real usages to read the correct line for their own context, then
  removed the now-fully-unused `company.whatsapp` config key entirely
  (and its now-dead `COMPANY_WHATSAPP` line from production `.env`)
  rather than leave a stale, confusing generic setting sitting around for
  a future page to accidentally reach for again.
  7 new Pest tests (one per fixed page, asserting the correct number
  renders and the wrong one explicitly does not), full suite green, Pint
  clean. No migration. Deployed same session, verified live.
- **2026-09-12 (later same day) — Pre-emptive fix, not a live incident: a
  second Hindi-language Meta ad variant's goal/budget questions would have
  silently missed the whole recommendation funnel, same failure class as
  the 2026-09-09 Hindi-form incident.** Owner shared the exact new ad copy
  before launch and asked whether the funnel would work the same way.
  Checked `ImportMetaLead::matchGoal()`/`matchBudget()`/`matchBudgetRange()`
  against the literal new questions/options rather than assuming: both
  question KEYS use different Hindi words than the ones the 2026-09-09 fix
  gated on — this ad asks "...सबसे बड़ी **ज़रूरत** क्या है?" ("need," not
  लक्ष्य "goal") and "...कितना **खर्च** कर सकते हैं?" ("spend," not बजट
  "budget") — so both field-key gates would have skipped these fields
  entirely, exactly like the original incident. Separately, all 4 goal
  OPTIONS mix English words into Devanagari text (`leads`, `ranking`,
  `online`, `Expert`) in ways the existing pure-Devanagari needles
  (`ऑनलाइन बढ़ाना`, `लीड्स प्राप्त करना`) don't match — e.g. "online
  बढ़ाना" (Latin "online") is a different string than "ऑनलाइन बढ़ाना"
  (Devanagari "online"). The budget side's number-counting logic needed no
  changes — it's phrase-agnostic and already handled ₹-range/plus answers
  correctly once the key gate passes.
  Extended both key gates to also accept `ज़रूरत`/`खर्च`, and added 4 new
  mixed-script needles to `matchGoal()` (`leads प्राप्त करना`, `ranking
  पाना`, `online बढ़ाना`, `पक्का नहीं`) — sourced from the ad's own real
  copy the owner pasted ahead of launch, not a live lead's answer, which
  is a deliberate one-time divergence from this codebase's usual
  "never fabricate a Hindi match, confirm against a real lead first"
  discipline (see [[feedback-gotchas]]): confirmed via AskUserQuestion
  that fixing pre-launch was worth that tradeoff rather than repeating the
  #370/#371 incident a third time. If Meta's actual slugification of this
  mixed-script text differs from what these tests assume, the first real
  leads from this ad are the fallback check.
  8 new Pest tests (4 goal-option cases + 4 budget-band cases, all using
  this ad's exact copy), full suite green (60/60 in this file), Pint
  clean. No migration, no deploy required beyond the next normal git pull
  (pure parsing-logic change, no schema/route/menu changes).
- **2026-09-12 (same day, minutes later) — Correction, caught by the owner
  from a real live lead (#407) within minutes of the deploy above: the
  fallback check landed immediately, and it disagreed with the ad copy
  the fix above was built from.** Investigated the real stored Note text
  for #407 directly rather than guessing again: Meta's actual field
  KEYS matched the new gates fine (`ज़रूरत`/`खर्च` both present, confirming
  that half of the fix), but the goal option VALUES Meta actually sends
  are pure-Devanagari transliterations — `google_पर_बेहतर_रैंकिंग_पाना`
  (रैंकिंग, not the Latin "ranking" the pasted ad copy used) — not the
  mixed English/Devanagari text the owner's pasted copy suggested. The
  `ranking पाना`/`online बढ़ाना`/`leads प्राप्त करना` needles added minutes
  earlier never matched anything real and were dead code from the start.
  **Widened the read to all 21 real leads carrying this ad's answers**
  (`whereHas('notes', ...'ज़रूरत'... 'खर्च'...)`), not just #407 — found
  this ad has actually been running since **2026-08-08** (lead #125), not
  newly launched as the owner's question implied; 19 of the 21 are
  already `contacted` by staff (who'd evidently been reading the Hindi
  text and hand-setting goal/budget_range through the existing picker UI
  as a manual workaround), 2 are `lost`, only #407 itself was untouched.
  Replaced the 3 wrong guesses with the confirmed real phrase
  (`रैंकिंग पाना`) and removed the ones that had no real counterpart at
  all (`leads प्राप्त करना`/`online बढ़ाना` — GenerateLeads/GrowBusiness's
  ALREADY-confirmed #370/#371 needles cover the real Devanagari text
  fine on their own). `पक्का नहीं` (NotSure) was independently confirmed
  correct against real leads #391/#404 (Meta's real answer is
  `पक्का_नहीं_–_एक्सपर्ट_की_सलाह_चाहिए` — एक्सपर्ट, not Latin "Expert",
  but "पक्का नहीं" alone matches either way). Tests rewritten against
  these 4 real leads' exact stored text instead of the ad-copy guesses.
  **Real lesson, not just this one incident**: ad copy a person retypes
  from memory/screenshot when relaying it is not the same ground truth
  as the literal bytes Meta's Graph API actually sends — the earlier
  "confirmed against the ad's real copy" framing in the entry above
  should have read "confirmed against the OWNER's transcription of the
  copy," a materially weaker claim; the very next real lead is a strictly
  better and cheap-to-get source of truth once the field is genuinely
  live, and should be checked before treating a pasted question as
  ground truth for a needle addition, not just relied on after the fact
  when something looks wrong. See [[feedback-gotchas]].
  **Backfill, data-only, explicitly no messaging**: confirmed via
  AskUserQuestion that the 16 affected leads (of the 21) missing at
  least one of goal/budget_range/estimated_value should be corrected on
  the Lead record itself only — never calling
  `GenerateLeadRecommendation::handle()` for the backfill, since that
  would dispatch a real WhatsApp "here's your recommendation" message via
  `SendOfferRecommendationReadyJob`/the VA invite job to leads who, in 19
  of 21 cases, staff already personally contacted (or, in 2 cases,
  already marked Lost) — an automated message arriving on top of that
  would read as a confusing, unprompted re-engagement, not a fix. Ran via
  the standard read/write/reflection scratch-script pattern (private
  `matchGoal()`/`matchBudgetRange()`/`matchBudget()` re-invoked via
  Reflection against each lead's own already-stored "Additional form
  answers" note text, values written to the Lead only where the target
  field was still null — 5 of the 21 already had both fields staff-set
  by hand and were correctly left untouched).
  The 8 goal/budget Pest tests added minutes earlier were rewritten
  against these 4 real leads' exact stored text (no new tests added this
  round — same 60/60 in this file), Pint clean, deployed same session
  (`git pull`+view-cache only, no migration).
- **2026-09-12 (same day, right after) — "What are they looking for?"
  capture panel on the Lead page collapses to a one-line summary once
  goal/budget/links are already known, instead of always showing the
  full edit form.** Owner flagged it from a real screenshot (lead #406,
  a Meta lead whose goal/budget/est. value were ALL already correctly
  auto-captured from the form): the panel unconditionally rendered two
  `<select>` dropdowns (pre-filled, duplicating the Goal:/Budget: lines
  already shown in the header two lines above) plus Website URL/GBP link
  inputs (ALSO duplicating the header's own Website:/GBP link: lines) —
  4 fields shown twice on the same page, "eating space" for no reason
  once a Meta lead's own form already answered them. `leads/show.blade.php`
  now computes `$goalCaptureNeedsAsking` (true when goal is null, OR
  budget_range is null, OR the goal needs a Website/GBP link that isn't
  captured yet) and wraps the panel in `x-data="{ editing: ... }"` —
  matching this exact file's own existing Alpine toggle convention (the
  Reassign panel, `x-data="{ open: false }"` + `x-show` + `x-cloak`) —
  collapsed to "🎯 Goal, budget & links already captured." + an Edit
  link when nothing is missing, expanded to the original full form
  otherwise (or immediately if validation errors exist, so a rejected
  submission doesn't disappear). The dropdowns/inputs and their pre-fill
  values are unchanged — this only wraps them in a show/hide toggle, no
  new fields, no route/controller change. **Real, but expected, gotcha
  hit writing the tests**: `x-show` keeps BOTH the collapsed-summary and
  expanded-form markup in the server-rendered HTML at all times (Alpine
  only toggles visibility client-side after JS runs) — so a Pest
  `assertDontSee` on the collapsed-state summary text always fails
  regardless of state, since that div is unconditionally present; the
  correct thing to assert from an HTTP test is the PHP-computed
  `editing: true`/`editing: false` value serialized into the `x-data`
  attribute itself, not which block is visually shown. 3 new Pest tests
  written against that signal (collapses when everything's known, stays
  expanded when goal or budget is missing, stays expanded when a
  link-needing goal has no website/gbp yet) plus the 3 pre-existing
  capture-panel tests re-verified unaffected, full suite green, Pint
  clean. Visually confirmed both states end-to-end in a real local
  browser session (Alpine actually toggling, not just the HTML assertion)
  before shipping — a throwaway `SMOKETEST Visual Check` lead created,
  clicked through both collapsed and expanded states, then deleted. No
  migration, no route change — deploy is `git pull`+view-cache only.
- **2026-09-12 (same day, minutes later) — Correction: the all-or-nothing
  toggle above was too coarse, caught by the owner from the SAME real
  lead (#406) right after deploy.** #406's goal (Grow My Business
  Online) and budget (Under ₹3,000) were both already known, but that
  goal still needs a Website/GBP link and neither was captured yet — so
  under the first version's single `editing` flag (true whenever
  ANYTHING was missing) the whole panel stayed fully expanded, still
  showing the two duplicate Goal/Budget dropdowns the owner had just
  asked to stop seeing. Split into two independent concerns instead of
  one flag: `$goalBudgetKnown` (drives whether the Goal/Budget
  `<select>` pair collapses to a compact "Goal: X · Budget: Y" line —
  now collapses the instant BOTH are known, regardless of link status)
  and `$linksStillNeeded` (drives whether the Website URL/GBP link
  inputs + Save button stay visibly reachable — shown via
  `x-show="editing || {{ $linksStillNeeded ? 'true' : 'false' }}"`, so
  they're live without a click whenever there's a genuine outstanding
  ask, exactly mirroring the pre-existing "Ask for their Website or GBP
  link" banner's own condition). `$fullyCaptured` (`$goalBudgetKnown && !
  $linksStillNeeded`) picks which one-line summary text to show when
  collapsed. **Real null-pointer bug caught by the test suite before
  shipping, not live**: the first draft's collapsed-summary branch read
  `$lead->goal->label()`/`$lead->budget_range->label()` unconditionally
  in the `@else` (not-fully-captured) branch — since Blade renders BOTH
  the collapsed and expanded markup unconditionally (Alpine's `x-show`
  only hides via client-side JS, never removes from the server response),
  this branch executes even for a lead where only ONE of goal/budget is
  set, e.g. the "keeps the panel expanded while something is missing"
  test's own fixture — crashing every existing lead-show test that hit
  it with "Call to a member function label() on null." Fixed with
  `?->label() ?? '—'` on both — a second instance of the exact same
  x-show-always-renders-both-branches gotcha this feature's own first
  version had already surfaced once.
  Rewrote the affected test to assert the new split behavior directly
  (`editing: false` even though a link is still missing, the "Goal: X ·
  Budget: Y" text present, `x-show="editing || true"` literal confirming
  `$linksStillNeeded` evaluated correctly) plus a `x-show="editing ||
  false"` assertion on the fully-captured case. Full 17-test
  `LeadGoalCaptureTest` suite green, all 376 tests in `tests/Feature/Leads/`
  re-verified green (no other view/test in that directory touches this
  panel, checked rather than assumed), Pint clean. Visually re-verified
  all 3 real states end-to-end in a local browser before shipping again
  (3 throwaway `SMOKETEST State A/B/C` leads spanning fully-captured,
  goal+budget-known-link-missing, and nothing-known — matching #406's own
  exact shape for state B — all deleted after). No migration, no route
  change — deploy is `git pull`+view-cache only.
- **2026-09-13 (same day) — Two real, distinct wadesk.in-side gaps found
  while the owner spot-checked a live WhatsApp conversation (lead #382):
  (1) the offer/recommendation funnel's WhatsApp templates had been
  approved by Meta all along but never synced into wadesk.in's own
  database, so every send 404'd; (2) wadesk.in's own after-hours freeform
  AI assistant has no idea the unified 4-offer matrix exists at all.**
  Owner asked to verify a real conversation where the AI recommended the
  GBP ₹120 offer to a lead whose answer ("Generate More Leads" + "Under
  ₹3,000") the matrix itself resolves to `lead_generation_audit` (₹299) —
  confirmed via `OfferRecommendationMatrix::for()` directly. Root-caused
  by reading wadesk.in's own source (this session has local file access
  to that sibling repo, though no SSH to its VPS): `ai-reply.ts`'s
  `buildLeadContextBlock()` only ever reads
  `context.visibilityAuditOfferUrl` — a GBP-only field computed via
  `VisibilityAuditFunnelMetrics::isVisibilityAuditCohort()` — with no
  equivalent for the other 3 offers at all, because `/api/leads/context`
  (built 2026-07-16/08-20/09-08, before the unified funnel existed
  2026-09-12) was never revisited when that shipped.
  **(1) Fixed live, no code change needed**: opened wadesk.in's own
  Templates page (already-authenticated browser session) and found
  `offer_recommendation`/`offer_recommendation_recovery`/`offer_recovery`
  genuinely absent from its list — not pending, not rejected, just never
  created there at all. Clicked **Sync from Meta**; all 3 appeared
  immediately, Approved, exact names matching the CRM's own config. This
  was the sole root cause of the "zero sends ever" finding from earlier
  the same day — Meta had approved them all along, nobody had run this
  one sync step. Checked `SendOfferRecoveryNudgeJob`: a failed attempt
  never sets `nudged_at`, so the ~45+-lead backlog needs no manual
  backfill — the existing `app:send-offer-funnel-recovery-nudges` cron
  (every 30 min) will pick every one of them up and succeed automatically
  now that the templates exist. Confirmed via AskUserQuestion not to
  force it manually — letting the natural cron cycle handle it was fine.
  **(2) Built the fix, CRM-side deployed, wadesk.in-side needs the
  owner's deploy**: `LeadContextController::show()` now also returns
  `recommended_offer_name`/`recommended_offer_price`/
  `recommended_offer_url` whenever `recommendation_offer_key` resolves to
  one of the 3 non-GBP offers (via `OfferKey::tryFrom()` +
  `Lead::recommendationUrl()`, the same tracked `/offers/recommendation/
  {token}` entry point `SendOfferRecommendationReadyJob` already points
  at) — deliberately omitted when the resolved offer IS GbpAudit, since
  `visibility_audit_offer_url` already covers that case through its own
  funnel-tracking `.enter` redirect hop, which must stay the one used for
  GBP so its `LandingViewed` event keeps firing. 3 new Pest tests, full
  `LeadContextTest` suite green (24/24), Pint clean, no migration — pure
  additive JSON fields, deployed same session (`git pull`+view-cache
  only, no route change since the route itself didn't change).
  wadesk.in's own side (`crm-lead-context.ts`'s `CrmLeadContext`
  interface + `ai-reply.ts`'s `buildLeadContextBlock()`, preferring the
  new fields over the GBP-only one when both are present) was written
  locally in that sibling repo this same session but NOT deployed — no
  SSH access to that VPS from this environment; the owner needs to run
  its usual `git pull && docker compose up -d --build` themselves. See
  [[wadesk-multinumber]] for the full investigation and evidence.
- **2026-09-13 (same day, after the owner's own wadesk.in deploy) — Third
  real bug in the same offer-funnel WhatsApp chain, found by manually
  re-triggering the send for a real lead (#382) rather than assuming the
  wadesk.in template-sync fix was the whole story.** Owner asked to
  correct lead #382's stale `goal` (see the entry above — a second Meta
  form resubmission's answer had been silently discarded by the
  never-overwrite-an-existing-goal rule). Fixed the goal, called
  `GenerateLeadRecommendation::handle()` to regenerate — service_id/
  recommendation_offer_key all recomputed correctly — but manually ran
  `queue:work --stop-when-empty` to actually process the resulting
  `SendOfferRecommendationReadyJob` for real (not `Queue::fake()`'d),
  and it STILL failed: Meta returned `(#132000) Number of parameters
  does not match the expected number of params`. Checked the log for
  every other lead — the owner's own natural 30-min recovery-nudge cron
  had already run (05:31 UTC) and hit the exact same error across the
  entire ~45-lead backlog. Root cause: `offer_recommendation`/
  `offer_recommendation_recovery`/`offer_recovery` are bilingual
  templates (English `{{1}}` + Hindi `{{2}}`, both meant to be the
  lead's own name — confirmed by re-reading each template's actual body
  text on wadesk.in's Templates page) but `SendOfferRecommendationReadyJob`/
  `SendOfferRecoveryNudgeJob` only ever sent one variable
  (`[$lead->name ?: 'there']`) — the exact same bilingual-template
  variable-count gotcha already hit and fixed once before for
  `lead_welcome`/`lead_checkin` (2026-09-09 entry above), recurring here
  because these 3 templates were never checked against that precedent
  when built. Fixed both jobs to send the name twice
  (`[$lead->name ?: 'there', $lead->name ?: 'there']`), matching
  `lead_welcome`'s own established `[name, service, name, service]`
  shape. 2 existing Pest tests updated (`variables` assertion in both
  job test files) to expect the 2-element array.
  **Real environment gotcha hit while verifying, unrelated to the fix
  itself**: the local Pest suite became completely unresponsive mid-session
  (confirmed by testing a totally unrelated, previously-fast, already-passing
  test, which also hung) — `Stop-Process`/`taskkill`/`kill -9` all failed
  to actually terminate the stuck `php.exe` processes, and `tasklist`
  kept reporting byte-identical memory usage across many checks minutes
  apart, suggesting stale/ghost process-table entries rather than
  genuinely live work. Pint (which doesn't touch the test DB) ran
  instantly and cleanly the whole time, and a direct `mysql -e "SELECT
  1"` responded instantly too, ruling out the database. Given the fix
  itself was a trivial, mechanically-identical repeat of an
  already-proven-correct pattern in this same codebase, and the bug was
  actively failing every real send in production at that moment,
  deployed without waiting for local Pest to recover — verified
  correctness directly against production instead, post-deploy:
  `SendOfferRecommendationReadyJob::dispatchSync(382)` (a real, immediate
  send, not queued) went from the `(#132000)` error to
  `recommendation_notified_at` actually being set and a genuine "✨
  Automated recommendation-ready message sent via WhatsApp" note landing
  on the lead — a real message reached Akash Surve. More authoritative
  than a unit test would have been for this specific class of
  external-API integration bug. Pint clean, deployed (`git pull`, no
  migration, no route change). The rest of the ~45-lead backlog is left
  to the next natural `app:send-offer-funnel-recovery-nudges` cron tick
  rather than manually swept, same reasoning as the earlier
  AskUserQuestion decision.
