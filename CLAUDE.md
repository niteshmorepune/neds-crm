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
Older entries (2026-06-10 through 2026-08-26) moved to `docs/decisions-log-archive.md` to keep this file under the context size limit — same format, nothing summarized or dropped. Recent entries continue below.

- **2026-09-16 (same day) — Real gap closed: `MergeLeadsRequest::
  MERGEABLE_FIELDS` (9 rep-pickable fields) never offered a choice on 49 of
  the `leads` table's other 58 columns, and nothing else carried them over
  either — a real investigation (same-day, read-only) found 15 of 38
  historical merges had silently dropped at least one real business-data
  field (Meta attribution, the offer-recommendation state, UTM, goal/
  budget, website/GBP links, `next_follow_up_at`, `telecaller_id`) onto the
  soft-deleted duplicate, unrecoverable through the UI. Two tasks, same
  session: fix it going forward, then recover what's already affected.
  **Task 1**: new `App\Actions\CarryOverMergeFields`, called from
  `MergeLeads::handle()` right alongside the existing `whatsapp_
  conversation_id` carry-over (2026-09-16, earlier same day). Non-unique
  fields fill the primary only if currently null — same shape as the
  existing `alternate_phone` auto-backfill in `LeadMergeController::
  store()`. `meta_leadgen_id` and the recommendation bundle
  (`recommendation_key`/`offer_key`/`token`/`generated_at`, moved together
  as ONE atomic unit, gated on `recommendation_token` alone) reuse the same
  null-the-duplicate-first-then-set-on-primary sequencing already proven
  for `whatsapp_conversation_id` — both are unique columns, and a soft
  delete alone doesn't free a unique slot. Also calls
  `GenerateLeadRecommendation::handle()` after carry-over (mirrors
  `LeadController::updateGoalCapture()`'s own pattern) — verified this
  never double-dispatches the first-touch WhatsApp job when the
  carried-over bundle is already resolved (recommendation_key already
  matches the matrix's own resolution for that cell, nothing dirty).
  **Deliberate, flagged limitation, not an oversight**: unlike
  `whatsapp_conversation_id`, this does NOT build a "both leads had one ->
  record a mapping" equivalent for `recommendation_token` — if the primary
  already has its own non-null token (both leads independently answered
  goal+budget and each generated their own recommendation — the real
  #346/#411 case), there's no `LeadWhatsappConversation`-style mapping
  table a second token could resolve through, so the duplicate's own token
  is genuinely left unrecovered in that one specific case. `MergeLeads` now
  requires `CarryOverMergeFields` as a constructor dependency — the 3 test
  files that previously did `new MergeLeads` were updated to
  `app(MergeLeads::class)`, matching this codebase's own existing
  convention (`FlagPossibleDuplicateLead`) for actions with real
  dependencies.
  **Task 2**: new `app:backfill-merge-field-carryover`, mirroring
  `BackfillMergedLeadWhatsappConversations` in shape and care. Deliberately
  does NOT hardcode the 15 affected merge ids — walks every merge
  breadcrumb note (the only general record of past merges, regardless of
  how initiated) and asks `CarryOverMergeFields` itself, read-only,
  whether that specific pair still has anything to carry over, so this
  stays a real re-runnable audit tool rather than a script frozen to one
  day's findings. Skips a self-referential note (`primary_id`/
  `duplicate_id` parsed to the same Lead — a real, harmless artifact from
  the 2026-09-16 #285/#420/#421 restore-and-remerge churn, not a distinct
  pair) and a duplicate that's since been restored (its own fields are
  live, independent data again). `--dry-run` supported; idempotent by
  construction — #420/#421 (already corrected by an earlier same-day
  one-off script) is naturally skipped since the primary already holds
  every value, no id exclusion needed.
  **Run for real against production, dry-run first**: dry-run reported 10
  affected merges, not 15 — reconciled and explained, not a bug: 5 of the
  original 15 (`#274/#284`, `#320/#321`, `#379/#350`) had the primary
  ALREADY independently holding its own value in every field the duplicate
  had (its own UTM source, its own `meta_leadgen_id`, its own goal) — the
  specific duplicate value in those cases is still permanently gone, but
  there's nothing safe to fill without violating "never overwrite the
  primary's own data." The real run matched the dry-run exactly, field for
  field: `#118`(`utm_campaign`), `#113`(3 UTM fields), `#263`/`#276`
  (`next_follow_up_at`), `#171`(UTM+`meta_leadgen_id`), `#285`(UTM+
  `meta_leadgen_id`), `#92`/`#95`(`utm_campaign`), `#287`(`goal`+
  `telecaller_id`), `#346`(`next_follow_up_at`). Verified `#346`/`#411`
  field-by-field as the named example: primary `#346`'s `next_follow_up_at`
  went from null to `2026-09-13 23:34:18` (the real recovery); primary
  `#346`'s own `recommendation_token` (`c2737882-...`) was correctly left
  untouched, NOT overwritten by duplicate `#411`'s own token
  (`39136a1b-...`), which remains genuinely orphaned on the still-trashed
  `#411` — confirmed live, exactly as predicted, no equivalent recovery
  path exists for it. Re-ran once more: `Affected 0 merge(s); 35 already
  had nothing missing` (25 originally-clean + 10 just-fixed) — real,
  verified idempotency, not just a dry-run claim. Extra safety check
  beyond what was asked: confirmed none of the 10 backfilled leads had a
  first-time goal+budget completion (every `recommendation_generated_at`
  was either still null or pre-dated the backfill), and the `jobs` queue
  table showed zero `SendOfferRecommendationReadyJob`/
  `SendVisibilityAuditFirstInviteJob` entries after the run — the backfill
  did not trigger any surprise WhatsApp sends to stale historical leads,
  the specific risk flagged during design (recovering old goal/budget data
  weeks after the fact could otherwise re-fire an automated "recommendation
  ready" message to a lead nobody's thought about in weeks).
  22 tests in `MergeLeadsActionTest.php` (8 new) + 9 new in
  `BackfillMergeFieldCarryoverTest.php`, full regression 980 tests green
  across `tests/Feature/Leads`/`Api`/`Integration` plus both backfill test
  files, Pint clean. No migration (pure PHP additions), deployed via the
  standard `git pull`. `docs/user-guides/sales.md`/`admin.md` updated to
  describe the auto-carry-over behavior to reps/admins.
- **2026-09-17 — Company-wide pause switch for the Next Action pop-up
  (`NextActionSetting`), Admin/Manager only.** Owner asked to disable the
  pop-up "for sometime." Clarified via AskUserQuestion that this is
  distinct from the pop-up's existing per-prompt Snooze (30m/2h/tomorrow,
  `NextActionSnooze`) — a plain ON/OFF kill-switch, no duration tiers, only
  Admin/Manager can flip it, and it pauses the pop-up for **every** user/
  role company-wide, not just the toggler's own view. Modeled as a
  singleton settings row (`NextActionSetting::current()`, same
  `firstOrCreate` pattern as `BillingSetting`/`AiUsageSetting`) rather than
  a new snooze variant, since "off until someone turns it back on" is a
  fundamentally different shape than a timed defer.
  `NextActionEngine::nextFor()` checks `NextActionSetting::current()->paused`
  first, before any source runs, with no role exception. New
  `NextActionSettingController` (index/pause/resume — two explicit actions
  rather than one toggle, so a double-submit can't flip it twice unnoticed)
  behind `menu.access:next-action-settings`, same no-Policy-class
  convention as Billing Settings/Festivals; new "Notification Settings"
  AdminConfig menu item (`roles => [UserRole::Manager]`, Admin implicit).
  7 new Pest tests (`NextActionSettingsTest` — view/pause/resume access,
  `updated_by` recorded) + 1 new `NextActionEngineTest` case proving the
  switch suppresses even the highest-priority source (Attendance) for both
  a Sales and an Admin user, full suite otherwise green (3686 passed, same
  one pre-existing unrelated `MeetingRequestTest` IST-window flake), Pint
  clean, no new Tailwind classes needed (grepped the compiled CSS first
  rather than assuming). Migrated + smoke-tested end-to-end against real
  local MySQL (pause → confirmed `paused=1`/`updated_by` set correctly →
  resume → confirmed `paused=0`), not just Pest.
- **2026-09-17 (later) — Expense reimbursement tracking: `reimbursed_at`/
  `reimbursed_by` on `expenses`.** Owner, from a screenshot of the
  Expenses page: many of these are paid out of a team member's own
  pocket first and owed back to them, with no way to track that.
  Confirmed 2 scope decisions via AskUserQuestion: a plain reimbursed
  toggle + date (not a partial-paid-back amount — these are small
  one-off expenses, always settled in full in one go, not milestone-
  billed), and yes to a dedicated "Owed to staff" filter + total,
  mirroring how Collections already surfaces outstanding money.
  New nullable `reimbursed_at` (date) / `reimbursed_by` (FK users)
  columns. One-click **Mark paid back** / **Undo** actions
  (`ExpenseController::reimburse()`/`unreimburse()`) stamp today's date +
  the confirming user for the common case; the Edit form also gained a
  **Paid back on** date field for backdating (e.g. reimbursed in cash on
  the spot) or correcting a mistake. `ExpenseController::update()`
  deliberately only touches `reimbursed_by` when `reimbursed_at` is
  actually changing — editing an unrelated field (e.g. description) must
  never reset who originally confirmed the reimbursement.
  **Real bug caught by the first test run, not shipped**: `ExpenseRequest::
  validated()` omits `reimbursed_at` from the array entirely (not as
  `null`) whenever the field is absent from the request at all, rather
  than submitted empty — `$data['reimbursed_at'] !== null` in `store()`
  then threw "Undefined array key" the moment a request didn't send that
  field (every pre-existing Expense test, since none of them knew about
  the new field). Fixed with `$data['reimbursed_at'] ??= null` inside
  `validatedWithPaise()` so the key is always guaranteed to exist as
  either `null` or a real date — same fix covers both `store()` and
  `update()`. 18 new Pest tests
  (`ExpenseReimbursementTest` — default/create-already-paid/one-click
  mark+undo/role gating/edit-form backdating/reimbursed_by-preserved-on-
  unrelated-edit/status-filter+totals), full suite otherwise green (3695
  passed; the one already-flagged `MeetingRequestTest` IST-window flake
  plus a newly-observed but pre-existing `DuplicateLeadAlertTest`
  "exactly the 14-day boundary" flake — same recurring wall-clock-
  boundary class, confirmed unrelated: that file is Lead-duplicate
  detection, never touched by this change), Pint clean. One new
  Tailwind class (`py-0.5`) wasn't in the compiled CSS — swapped for the
  already-compiled `py-1` rather than triggering a full asset rebuild for
  one badge's padding. Migrated + smoke-tested end-to-end against real
  local MySQL via `php artisan serve` + curl: created a real `SMOKETEST`
  expense, confirmed it showed **Owed**, clicked **Mark paid back**
  (verified `reimbursed_at`/`reimbursed_by` in the database), confirmed
  the status filter correctly included/excluded it, then deleted it
  through the app's own destroy route. Docs: `accounts.md`/`admin.md`
  extended (their PDFs regenerated; the other 8 unaffected PDFs
  discarded per the established PDF-isn't-byte-stable gotcha).
- **2026-09-17 (later) — Real gap, reported via a real example (lead
  Avinash Deshmukh): the automatic "no reply, send a WhatsApp check-in"
  follow-up still fired even after a telecaller had already phoned the
  lead.** Owner: "if human contact with the lead is done, then do not
  send any template to the lead, let the human decide if those templates
  to be send manually." `SendLeadWelcomeFollowUps` (the every-30-minute
  cron behind the automatic re-engagement check-in — 2026-09-09 entry
  above) only ever called `Lead::isAwaitingWelcomeReply()`, which checks
  the WhatsApp channel alone (an outbound staff reply or an inbound one).
  Every OTHER automated template in this family — `SendLeadWelcomeMessageJob`,
  `SendOfferRecommendationReadyJob`, `SendVisibilityAuditFirstInviteJob`,
  both recovery-nudge jobs — already guards on the broader
  `Lead::hasStaffEngagementSince()` (a phone call or a plain staff note
  counts as real engagement too, not just a WhatsApp reply — built
  2026-09-13 for exactly this class of bug, lead #322), but this one
  command was never updated to use it. Same bug, same root cause,
  recurring a third time in this codebase (see [[feedback-gotchas]]).
  Fixed with one line — `->reject(fn (Lead $lead) =>
  $lead->hasStaffEngagementSince($lead->welcome_message_sent_at))`
  chained onto the command's existing candidate filter — checked since
  `welcome_message_sent_at`, not lead creation, since that's the point
  this command itself measures "gone quiet" from. Deliberately fixed at
  this automatic call site only, NOT inside `SendLeadCheckInJob` itself
  — that job is also dispatched by the staff-facing manual **📱 Send
  WhatsApp check-in** button, which must stay unconditional so a human
  can still choose to send it regardless of prior contact, matching the
  owner's own explicit "let the human decide manually" framing. Audited
  every other wadesk.in `/api/send-template` call site in the same pass
  to confirm no other gap existed: the remaining ones
  (`SendVisibilityAuditInProgressJob`, `SendQuotationWhatsAppJob`,
  `SendVisibilityAuditReportJob`, `SendVisibilityAuditPaymentConfirmationJob`,
  `SendWhatsappHandoffMessageJob`) are all transactional sends tied to a
  concrete paid/staff-triggered event (payment received, quotation sent,
  deal won), not cold-outreach/recovery nudges, so "has staff already
  contacted this lead" isn't the right question for them and they were
  left untouched. Also flagged, not fixed (separate channel, not what was
  reported): `SendVisibilityAuditFirstInviteEmailJob`/
  `SendVisibilityAuditRecoveryNudgeEmailJob` still call the older, narrower
  `hasStaffWhatsappReplySince()` rather than `hasStaffEngagementSince()` —
  a real inconsistency, tracked in [[backlog]] rather than bundled into
  this fix. wadesk.in itself needed no change — the scheduling/suppression
  logic lives entirely on the CRM side; wadesk.in only ever receives the
  already-decided `/api/send-template` call.
  3 new Pest tests (`SendLeadWelcomeFollowUpsTest` — skips a lead staff
  already called, skips a lead staff already left a plain internal note
  on, still fires when the only engagement predates the welcome message
  rather than following it), full `tests/Feature/Leads`+`tests/Feature/Integration`
  suites green (889 passed, no regressions), Pint clean. No migration,
  no route/view/config change — deployed via a plain SSH `git pull`
  (PR #204, `24a3076`), verified live: `/login` 200, `schedule:list`
  still shows `app:send-lead-welcome-followups` on its `*/30 * * * *`
  cadence, today's production log clean of anything new (routine
  biometric-bridge noise only). Docs: `sales.md`/`telecaller.md`'s own
  description of the automatic check-in corrected to say a call or a
  plain note cancels it too, not just a WhatsApp reply (their PDFs
  regenerated; the other 8 unaffected PDFs discarded per the established
  PDF-isn't-byte-stable gotcha).
- **2026-09-17 (later still) — Real gap reported live (lead #443,
  Ashavini Sonawne): a Meta lead's email clearly on the submitted form
  wasn't captured in the CRM at all; the owner separately flagged company
  and goal missing on the same lead too.** Root-caused, not guessed:
  Meta's Lead Ad flow auto-sends a WhatsApp message right after the form
  submit, which `WhatsappWebhookController::handleUnmatchedNumber()`
  often lands as the Lead a few seconds BEFORE `ImportMetaLead`'s own
  webhook runs — that `Lead::create()` call only ever sets name/phone,
  nothing else. `ImportMetaLead::attachToExistingLead()` then runs
  moments later with the real, structured Graph API `field_data` (the
  authoritative source, not a text-parse) and backfills `service_id`/
  `estimated_value`/`goal`/`budget_range` "only if currently null" —
  but `email`/`company` were never added to that same whitelist, even
  though `parseFieldData()` already computed them correctly every time.
  The real values were silently discarded on every lead that hit this
  exact race, forever, with the raw text only ever visible buried in a
  note nobody reliably reads field-by-field. Fixed by adding `email`/
  `company` to the existing `$fill` array (same "only if currently null"
  guard, `attachToExistingLead()`'s own signature extended to take the
  already-parsed `$fields` array it just wasn't being passed before).
  Deliberately did NOT extend this to `name` — the WhatsApp profile name
  and the Meta form's typed name can legitimately differ (this exact
  lead: "Ashavini Sonawne" vs. the form's "Ashvini Kulkarni" — a real
  maiden/married-name-shaped difference, not obviously a bug), and unlike
  email/company the existing lead's name is never actually null (always a
  real value or the "WhatsApp Inquiry" placeholder), so overwriting it
  needs its own deliberate placeholder-detection decision, not a blind
  null-check — left alone rather than silently deciding which name wins.
  **Goal was a separate, unrelated bug on the same lead**: its own answer
  ("अधिक_leads_प्राप्त_करना") mixes the Latin word "leads" into Devanagari
  phrasing — a THIRD real ad variant (campaign 120251465805080458),
  distinct from both previously-handled Hindi forms, confirmed against
  this lead's own stored note text (not a retyped guess, learning directly
  from the [[feedback-gotchas]] lesson the last two rounds of this exact
  mistake taught) — added as a new `matchGoal()` needle.
  3 new Pest tests (email/company backfill only-if-null + does-not-
  overwrite, the third Hindi goal variant against lead #443's real text),
  full `tests/Feature/Leads`+`tests/Feature/Integration` suites green
  (892 passed, no regressions), Pint clean. No migration — deployed via
  SSH `git pull` (PR #205, `a61c70e`), verified live (`/login` 200, new
  needle present in the deployed file, log clean).
  **Production backfill, not just a code fix**: a read-only audit found
  50 Meta leads with SOME field null, but narrowing to only leads that
  actually went through the `attachToExistingLead()` race path (detected
  via its own unique "Also submitted a Meta Ads form" note prefix — the
  only leads this bug could possibly have hit) cut that to 7 real
  candidates; the other 43 were leads whose ad form simply never asked
  for company at all, a legitimate absence, not this bug. Backfilled via
  a Reflection-based script that re-fetches each of the 7 leads' real
  Meta Graph API `field_data` by their own `meta_leadgen_id` (the
  authoritative source, not a note text-parse) and invokes the actual
  `parseFieldData()`/`matchGoal()` methods directly — reusing the real,
  already-fixed logic rather than a second hand-written copy that could
  drift from it. Dry-run first, then applied: 6 of 7 leads corrected
  (#235/#246 company, #415/#416/#435 email+company, #443 email+goal — the
  exact real values matching what was reported); #125 correctly left
  untouched, since its own real Meta form data confirms company was
  genuinely never submitted on that ad, not silently dropped. Left a
  visible `🔧 Backfilled from Meta form data` note on each corrected lead.
  Re-ran the narrow audit after: 0 remaining (idempotency confirmed live,
  not just claimed). Lead #443 verified individually, field by field, via
  tinker. Scratch scripts deleted from the server after.
- **2026-09-18 — Lead Generation "Next Action" column (Phase 1, side-by-
  side trial per the agreed [[lead-next-action-column-plan]] memory) +
  the `next_follow_up_at` habit-gap fix.** Owner reported (screenshot)
  that the list's **Latest Note** column often shows something unhelpful
  at a glance (a raw call-outcome line, a Meta-import backfill note, a
  `[location]` placeholder) and asked for a computed "Next Action" column
  instead. Confirmed 3 scope decisions via AskUserQuestion before
  building, all recommended: ship it **alongside** Latest Note first
  (not a replacement yet), **deterministic only** (no AI polish this
  round), and **informational text only** (no clickable actions yet).
  New `App\Services\LeadNextActionAdvisor` — same shape as the existing
  `LeadCallTimingAdvisor` (a plain service, not a `NextActionSource`,
  since that contract is per-USER for the popup; this is per-LEAD, a
  column value) — returns the first-non-null result of an 8-rule
  priority chain reusing existing signals: stalling objection ≥3 days
  quiet (mirrors `ObjectionFollowUpDueSource`'s own staleness check,
  applied to one record instead of a team scan) → `next_follow_up_at`
  overdue/due today (`Lead::isFollowUpOverdue()`/`isFollowUpDueToday()`
  directly, not `CallFollowUpDueSource` — that source actually keys off
  the unrelated `CallLog.follow_up_at`, per-user; the plan's own gap
  example, lead #339, was specifically about `Lead.next_follow_up_at`) →
  a meeting within 24h → goal captured but Website/GBP link missing →
  goal = Not Sure → welcome sent, no reply (`isOverdueForWelcomeReply()`)
  → never called yet, best hour to call (`LeadCallTimingAdvisor`) →
  fallback to today's latest-note gloss, always non-null.
  **Deliberate divergence from the memory plan's own caching proposal**:
  the plan assumed live computation for 291+ leads per page load was too
  expensive and proposed a cached `next_action_hint` column + an Observer
  invalidated on 6+ event types (note added, call logged, stall_reason/
  goal/next_follow_up_at changed, meeting created). Checked
  `LeadController::index()` first — it already computes the "best time to
  call" badge live, per request, for only the CURRENT PAGE's 15 rows
  (`callBadges`, after pagination slicing), never all 291+ — pagination
  already caps the real cost. Mirrored that exact pattern
  (`$nextActionHints`) instead of adding a cache column: simpler, no
  invalidation surface to keep in sync (a recurring source of stale-cache
  bugs elsewhere in this app's own history), no migration needed.
  `meetings:id,meetable_id,meetable_type,title,occurred_at` added to the
  index query's existing eager-load list for the new meeting-soon rule.
  **Habit-gap fix**: the plan flagged that `next_follow_up_at` stays null
  even when a note describes a concrete commitment (lead #339: "we'll
  connect today at 5pm"), since setting it required a separate trip to
  the Lead/Deal Edit form. Scoped as a UI-prominence fix only, not date-
  detection-from-text (explicitly deferred, per the plan). Added an
  optional **Next follow-up** datetime field directly to `RecordNotes`'
  Add Note form (`canSetFollowUp()`, gated like `canDraft()`/
  `canSummarize()` on `canManage` + `Lead|Deal`) — filling it in on
  `addNote()` updates the record's `next_follow_up_at` in the same
  action; same parsing convention as `LeadController::payload()`
  (`Carbon::createFromFormat('Y-m-d\TH:i', ..., display_timezone)->utc()`).
  Leaving it blank never touches an existing value — same "only writes
  what's actually filled in" convention already established for
  stall_reason/goal capture on this app's other incidental-field forms.
  25 new Pest tests (17 `LeadNextActionAdvisorTest` — all 8 rules +
  priority ordering + closed-lead exclusion, 2 `LeadNextActionColumnTest`
  — render, 6 `RecordNotesFollowUpTest` —
  set/blank-preserves/validation/reset, both Lead and Deal), full suite
  3727 green (same one pre-existing unrelated
  `MeetingRequestTest` IST-window flake), Pint clean. No migration, no
  menu/route change — deploy is `git pull`+view-cache only. Docs:
  `sales.md`/`telecaller.md` extended (their PDFs regenerated; the other
  8 unaffected guides' regenerated-but-unchanged PDF bytes discarded per
  the established PDF-isn't-byte-stable gotcha). Phase 2 (swap Latest
  Note out once picks are validated against real leads) and Phase 3
  (optional AI polish, reuse on VA Recovery/Stalling/My Day) deliberately
  not built yet — see [[backlog]].
- **2026-09-18 (later) — Next Action column: specific-action upgrade +
  Latest Note retired, deliberately combining a free structural win with
  new AI.** Once PR #206 went live, the owner asked for genuinely
  specific actions ("Call the Lead", "Send the Quotation", "Remind him to
  visit the office", "Confirm the time to call") instead of generic ones,
  and — after weighing a deterministic-only vs. AI vs. both approach via
  AskUserQuestion — said plainly "I think we need AI here to help in
  this." Mid-build, the owner separately said the list no longer needs
  its own "Latest Note" column now that Next Action is specific enough
  (still shown on the lead's own page) — retired here too.
  **Real discovery before writing any new AI code**: `App\Jobs\
  DetectCallFollowUpCommitment` (live since 2026-08-31) already reads a
  logged call's own notes and writes a specific imperative
  (`CallLog.next_action`, e.g. "Send proposal") whenever a rep leaves both
  `follow_up_at`/`next_action` blank after a connected call — the Lead
  Generation list simply never read it. New
  `LeadNextActionAdvisor::callLogFollowUpWithInstruction()` surfaces that
  text verbatim (checked before the generic Lead-level `followUpDue()`)
  — a real, free win requiring zero new AI calls, since the AI work had
  already happened. Also found and fixed a related, real gap while
  reading that code path: `RecordNotes`'s own "This was a call" shortcut
  creates a `CallLog` directly, bypassing `CallLogController::store()`
  entirely — meaning this exact detection job had NEVER once fired for
  a call logged that way, since nothing ever dispatched it there. Now
  dispatched from that shortcut too, mirroring the controller's own
  guard exactly.
  **The genuinely new AI piece**: `App\Jobs\
  DetectLeadNoteFollowUpCommitment` + `App\Notifications\
  LeadFollowUpAutoSet`, a plain-note counterpart to
  `DetectCallFollowUpCommitment` — that job is CallLog-specific, so a
  commitment written into an ordinary Add Note ("he said he'll drop by
  Saturday") was never read by anything. Mirrors the same grounded,
  review-not-silent-override contract byte-for-byte (never overrides a
  rep-set `next_follow_up_at`, silent no-op on AI failure/no-commitment/
  disabled, notifies the note's own author so they can adjust it) rather
  than inventing a separate, less-trusted mechanism. Writes to new
  `leads.ai_detected_next_action` (nullable string), which
  `LeadNextActionAdvisor::followUpDue()` shows in place of the generic
  "Follow up now — overdue" text whenever set.
  **Real bug caught by the first test run, not shipped**: the new
  `Lead::saving()` guard ("clear `ai_detected_next_action` whenever a
  human touches `next_follow_up_at`, so stale AI text never lingers
  attached to a date it didn't generate") fired on `isDirty()` alone —
  which is true for every attribute on a brand-new model's very first
  `create()`, since Eloquent has nothing to compare against yet. That
  meant a lead created with both fields set together — exactly what the
  AI job's own atomic write looks like — immediately wiped the value it
  was just given, the instant a test (or, in principle, a future
  seeder/import) exercised that shape via a normal `save()`. Fixed by
  also requiring `$lead->exists` — true only once a model has already
  been persisted, so the guard now only ever fires on a genuine update to
  an existing lead, never at creation. The AI job's own real writes were
  never actually at risk (`saveQuietly()` skips this hook, and every
  other model event, entirely) — this was a test-fixture-shaped gap that
  would have looked identical in production the day anything else ever
  created a Lead with both fields pre-set outside the job itself.
  Two new structured rules, no AI involved: `sendQuotation()` (a
  converted lead's open Deal — not Won/Lost — with no Quotation that's
  ever reached Sent/Accepted) and `scheduleMeeting()` (that Deal at
  Proposal/Negotiation with zero Meetings ever logged against the lead —
  Deals have no meetings relation of their own in this app, see
  `Lead::meetings()`). `neverCalled()` reworded from "Try: 9 AM, 11 AM"
  to "Call the lead — best around 9 AM, 11 AM" / "Try calling again —
  best around …" per the owner's own phrasing.
  `LeadController::index()`'s `callLogs` eager-load gained
  `follow_up_at`/`next_action` columns for the new rule; `latestNote`
  stays eager-loaded (still the advisor's own final fallback) even though
  the list no longer renders it as its own column. Removed the "Latest
  Note" `<th>`/`<td>` from `leads/index.blade.php`, empty-state colspan
  dropped back to 8/9.
  **Test fallout from removing the column, fixed not ignored**: two
  pre-existing `LeadCrudTest` cases asserted directly against the removed
  "Latest Note" column's own truncation length (60 chars) and dash
  placeholder — rewritten to assert the Next Action column's fallback
  behavior instead (50 chars, matching `LeadNextActionAdvisor::
  fallbackNote()`'s own limit; "— No activity yet" instead of a bare
  dash), with an explicit `source: ColdCall` on the fixture so
  `LeadCallTimingAdvisor`'s capture-hour signal can't intermittently
  produce a "Call the lead" recommendation ahead of the fallback being
  reached (same isolation convention `LeadNextActionAdvisorTest`'s own
  `coldCallLeadForNextAction()` helper already uses).
  New migration (`leads.ai_detected_next_action`), migrated locally. 41
  new/updated Pest tests across `LeadNextActionAdvisorTest`,
  `LeadNextActionColumnTest`, `LeadCrudTest`, and a new
  `LeadNoteFollowUpDetectionTest` (mirrors `CallFollowUpDetectionTest`'s
  own fixture shapes so the two mechanisms can't silently drift apart in
  what they promise), full suite green, Pint clean. `sales.md`/
  `telecaller.md` extended (their PDFs regenerated; the other 8
  unaffected guides' regenerated-but-unchanged PDFs discarded per the
  established gotcha).
- **2026-09-18 (later still) — AI-driven, full-context Next Action
  (`App\Jobs\AnalyzeLeadNextAction`), replacing the deterministic advisor
  as the PRIMARY source; the deterministic chain becomes its fallback.**
  Owner reviewed three real leads (#435 Prem Motors, #372 Rajesh
  Choudhary, #340 Sagar Shivaji Salgar) the same day the deterministic
  Next Action column shipped — root cause for all three turned out to be
  a backfill gap (calls logged via the "This was a call" note shortcut
  before `DetectCallFollowUpCommitment` was wired to fire from that path;
  fixed + backfilled separately, not part of this entry). But investigating
  them surfaced a real, deeper limitation: a rule keyed on "the oldest
  unresolved commitment" has no way to know a LATER event supersedes it —
  Sagar's lead had agreed to a Google Meet on 8 Sep, then went unanswered
  on a follow-up call on 17 Sep, and the deterministic chain kept
  surfacing the stale Google Meet line with no awareness the situation
  had moved on. Owner: "AI has to do it more precisely every case and
  [do a] lead page data analysis and tell the exact next action for the
  human now." Confirmed via AskUserQuestion: re-analyze a lead on every
  relevant CHANGE (event-driven), not on a schedule or on-demand-only —
  the same "cache + observer-driven invalidation" shape the original
  Next Action plan had proposed and then deliberately skipped (pure live
  computation was cheap enough for a deterministic rule chain; a genuine
  full-page AI read is a different cost profile entirely).
  New `leads.ai_next_action_hint`/`ai_next_action_generated_at` columns
  (deliberately separate from the existing narrower `ai_detected_next_action`,
  which stays exactly what it was — `DetectLeadNoteFollowUpCommitment`'s
  own plain-note-commitment signal). `AnalyzeLeadNextAction` reads a
  lead's full state (goal/budget/stall/status/next_follow_up_at), its VA/
  offer funnel stage (reuses `VisibilityAuditFunnelMetrics::funnelStatusFor()`,
  not re-derived), and a combined, chronologically-ordered timeline of its
  last 15 notes+calls+meetings, and asks Claude (haiku) for ONE precise
  imperative next action, explicitly told to weigh the most recent
  timeline items most heavily. Skips Lost outright; deliberately does
  NOT skip Converted (unlike `LeadStatus::isOpen()`) since "send the
  quotation"/"schedule the meeting" are exactly the kind of hint a
  Converted lead needs. Silent no-op on any AI failure, same contract as
  every other AI job in this app — never overwrites an existing cached
  hint with null/garbage.
  `Lead::queueNextActionAnalysis()` centralizes the "what counts as a
  relevant change" list in one place rather than duplicating it at every
  call site: `CallLogController::store()`, `RecordNotes::addNote()` (both
  the plain-note and logged-as-a-call branches), `MeetingImport`'s three
  creation methods (manual/external, scheduled Google Meet, imported past
  event), `LeadObserver::updated()` for stall_reason/goal/budget_range/
  website_url/gbp_url/next_follow_up_at/status changes, and directly from
  `GenerateLeadRecommendation::handle()` and both
  `DetectCallFollowUpCommitment`/`DetectLeadNoteFollowUpCommitment` once
  either sets a real commitment — the latter three all write via
  `saveQuietly()`, so `LeadObserver` never sees those changes and each
  dispatches the re-analysis itself directly.
  `LeadNextActionAdvisor::hintFor()` now checks the cached AI hint FIRST,
  ahead of every deterministic rule; that rule chain is unchanged and
  serves purely as the fallback for a lead the job hasn't analyzed yet
  (brand new, nothing logged, or AI disabled) — the "no per-row AI cost"
  constraint that made the original column deterministic-only still holds
  for this fallback path, since it only ever reads an already-computed
  cached column, never calls AI itself.
  **Real gotcha applied proactively, not hit**: the timeline-building code
  maps Eloquent collections (notes/calls/meetings) into plain arrays for
  sorting — the same `Collection::map()`-into-plain-arrays shape that has
  bitten this codebase's `merge()`/`flatMap()` calls before (see
  [[feedback-gotchas]]) — downgraded to a plain `Support\Collection` via
  `collect(...->all())` up front so nothing later in the chain can trip
  over `Eloquent\Collection::merge()`'s own `getKey()` assumption, even
  though the specific methods used here (`concat()`/`sortBy()`) don't
  actually call it.
  **Two pre-existing tests fixed, not the new code**: `LeadScoringTest`'s
  "does not re-score [for a] non-scoring field change" and "...already-
  terminal lead" cases both updated `next_follow_up_at` and asserted a
  blanket `Queue::assertNothingPushed()` — now genuinely wrong, since that
  field is a real trigger for this job too; narrowed both to
  `Queue::assertNotPushed(ScoreLead::class)`, which is what they actually
  meant. Same root issue, one more instance:
  `LeadNoteFollowUpDetectionTest`'s "never overrides a next_follow_up_at
  the rep already set" test set a manual date (a trigger) then asserted
  `Http::assertNothingSent()` about a DIFFERENT job entirely — added a
  scoped `Queue::fake()` around just that manual update so this job's own
  incidental (and, under the test suite's `sync` queue driver,
  synchronous) re-analysis can't pollute an assertion about
  `DetectLeadNoteFollowUpCommitment` specifically.
  62 new Pest tests (`LeadNextActionAnalysisTest` — prompt content,
  Lost-skip/Converted-no-skip, AI-failure/null-reply leaves the cached
  hint untouched, no model event fired; `LeadNextActionAnalysisDispatchTest`
  — every real call site fires, irrelevant fields/records don't; 3 new
  `LeadNextActionAdvisorTest` cases — AI hint outranks every deterministic
  rule, correct fallback when absent, not status-gated) plus the 2 fixed
  pre-existing tests, full suite otherwise green across every test file
  touching any changed class (~886 tests re-verified: `Ai/`, `Leads/`,
  `GoogleMeet/`, `Integration/`, `GenerateLeadRecommendationTest`, plus a
  second sweep for `calls.store`/`RecordNotes`/`MeetingImport` usages the
  first keyword search might have missed) — a full from-scratch
  `php artisan test` run was deliberately not attempted this session given
  the documented orphaned-`pest`-process risk on this dev machine (see
  [[local-dev-env]]), so this targeted sweep is the verification of
  record. Pint clean. No local Anthropic key configured (see precedent
  throughout this log), so the live AI call itself is only exercised
  through the Pest suite's faked HTTP layer, not a real API round-trip;
  smoke-tested the rest of the path against real local MySQL instead —
  migrated cleanly, `/leads` renders with no error, and a throwaway
  `ai_next_action_hint` set directly on a real local lead rendered
  correctly on the live list (`✨ …` label, "AI-analyzed … ago" tooltip)
  before being reverted. `sales.md`/`telecaller.md` extended to explain
  the ✨ marker and the new fallback framing; PDFs regenerated for both,
  the other 8 unaffected guides' regenerated-but-unchanged PDFs discarded
  per the established gotcha. Not yet merged or deployed — PR pending
  owner review (this is a new, ongoing per-lead AI cost, unlike a one-off
  backfill).
- **2026-09-18 — Real incident: Meta's own "click-to-WhatsApp" lead ad
  delivers ONE person through TWO uncoordinated channels with TWO
  different phone numbers, creating a permanent duplicate Lead every time
  — plus the wadesk.in-side goal-question flow re-asking on top of an
  unrelated automated message. Three CRM fixes + one wadesk.in fix + one
  production merge.** Owner reported (screenshots): "Babban Verama"
  appeared twice in Lead Generation (#445 "babbanv932", #446 "Babban
  Verama"), and separately, after he replied "Yes, call me" to an
  automated welcome message, wadesk's after-hours assistant answered with
  an unrelated "what's your biggest goal?" instead of acknowledging the
  callback. Root-caused against real production data, not guessed:
  Meta's own WhatsApp-relay message ("Hello! I filled out your form...
  Full name: X... Phone number: Y...") arrived from the person's REAL
  WhatsApp number (7408220959) 36 seconds before the separate, structured
  Graph API Lead Ads webhook arrived reporting the number he'd TYPED into
  the form instead (9823708625, a different number entirely).
  `WhatsappWebhookController::handleUnmatchedNumber()` had nothing to
  read that relay message's text, so it created a Lead named after his
  WhatsApp username ("babbanv932") — a name too weak for
  `DuplicateLeadDetector` to ever match against the real "Babban Verama"
  the Graph webhook created moments later under the other number. Two
  separate root causes compounded from there: the real Hindi/mixed-script
  goal answer ("अपने_business_को_online_बढ़ाना") didn't match any
  `matchGoal()` needle (same class of gap fixed 3 times before — see
  [[feedback-gotchas]]), so goal stayed null, triggering the generic
  `SendLeadWelcomeMessageJob` welcome instead of a real recommendation;
  and wadesk.in's `goal-flow.ts` has no awareness of that welcome having
  just been sent, so it read his reply to it as a blank-slate opener and
  fired its own goal question on top.
  **Fix 1 (CRM)**: `ImportMetaLead::matchGoal()` gained a 4th real Hindi
  goal-answer needle (`'अपने business को online बढ़ाना'`), confirmed
  against lead #446's own stored text, not a retyped guess.
  **Fix 2 (CRM)**: `Lead::findOpenByPhone()` now also checks
  `alternate_phone` — `Customer::findByPhone()` has always checked its
  own alternate_phone; the Lead-side twin never did. This is what lets
  Fix 3 below actually prevent the duplicate, not just detect it after
  the fact.
  **Fix 3 (CRM)**: new `WhatsappWebhookController::extractRelayFormFields()`
  recognizes Meta's fixed "Full name:/Phone number:/Email:/Company name:"
  relay template (only 1 real occurrence in production so far, but a
  reliable, low-false-positive-risk pattern to detect) and uses the REAL
  extracted name/email/company instead of the WhatsApp-profile
  placeholder, storing the embedded (different) phone as
  `alternate_phone`. Together with Fix 2, this means the REAL Graph
  webhook — arriving moments later — now finds and attaches to this SAME
  lead via `ImportMetaLead::handle()`'s own pre-existing race-condition
  lookup, instead of creating a second one. `DuplicateLeadDetector` also
  benefits as a backstop for any case this doesn't fully close, since it
  now has a real name to match on.
  **Fix 4 (wadesk.in)**: `goal-flow.ts` gained
  `isFirstReplyToOurOwnRecentMessage()` — when this is the very first
  inbound message a conversation has ever received AND wadesk already
  sent something (any kind) within the last 24 hours, skip the cold-open
  goal/budget ask for this one turn and let the normal Claude-drafted
  reply (which sees the full history) respond in context instead; the
  ask resumes normally on the lead's next message. Deliberately a LOCAL
  check against wadesk's own `message` table, not a round trip to the
  CRM's "awaiting welcome reply" signal — that signal is derived from
  Notes a separate, independent, fire-and-forget push has to land first,
  a real race this investigation confirmed isn't safe to depend on.
  **Fix 5 (wadesk.in, found while investigating why lead #445's own goal
  reply never stuck)**: `handleGoalAnswer()`/`handleBudgetAnswer()` only
  ever accepted a real WhatsApp interactive-list/button tap
  (`interactiveReplyId`) — a reply typed as plain text, even one
  byte-identical to the option's own title ("Grow My Business Online",
  confirmed against lead #445's real logged reply), was silently
  discarded with no write-back to the CRM at all. New `resolveOptionId()`
  falls back to an exact normalized-text match against the option title
  when there's no tap — deliberately EXACT match, not substring, since
  the budget options overlap on their numbers ("Under ₹3,000" vs
  "₹3,000 – ₹6,000") and a loose match there could misfile a reply into
  the wrong band.
  **Production data fix**: merged #445 into #446 via the real `MergeLeads`
  action (not raw SQL) — kept the ACTIVELY-messaging WhatsApp number
  (7408220959) as the canonical `phone`, the form-typed number as
  `alternate_phone`, all 10 notes from both threads correctly
  consolidated in chronological order, and confirmed the
  `lead_whatsapp_conversations` mapping table correctly routes #445's old
  conversation to the merged #446 going forward. Set `goal =
  GrowBusiness` directly on the merged lead afterward (now confirmed
  twice — the Meta form's own free text and his own WhatsApp reply both
  say the same thing) — deliberately via a quiet, direct write, NOT
  `GenerateLeadRecommendation::handle()`, since Neha had already told him
  on WhatsApp "I'll call you tomorrow after 10am" — an automated
  recommendation-ready message arriving on top of that live human
  conversation would be a confusing, unprompted re-engagement (same
  reasoning as the 2026-09-12 16-lead goal-backfill's own "no messaging"
  decision).
  CRM side: 3 new/updated test files (`LeadFindOpenByPhoneTest` new,
  `WhatsappWebhookTest`/`ImportMetaLeadJobTest` extended), full targeted
  suite re-verified across every `findOpenByPhone()` call site (~530
  tests: `WhatsappWebhookTest`, `ImportMetaLeadJobTest`,
  `DuplicateLeadAlertTest`, `DuplicateLeadDetectorTest`, every
  offer/VA-funnel/quotation test touching phone-matching), Pint clean.
  wadesk.in side: `npx tsc --noEmit` clean (this repo has no test suite —
  same "ships inert until verified live" contract as every prior
  wadesk.in change). wadesk.in's own deploy needs the owner's usual
  `git pull && docker compose up -d --build` (no SSH access to that VPS
  from this session).
- **2026-09-19 — Real incident, owner-reported via a wadesk.in inbox
  screenshot: a contact at Exim Internationals (existing client, active
  SEO + AMC) messaged the WhatsApp support line from a personal number
  never on file and sent a PDF named
  `Exim_Internationals_Website_Changes_Improvements.pdf`. With no way to
  recognise the number, the CRM filed it as a brand-new Lead (#452) and
  wadesk.in's own after-hours AI fired the generic "what's your biggest
  goal right now?" qualifying question at an existing client — owner:
  "the message from AI is not relevant," then "it is added as lead on
  the CRM too, which is again not correct."** Root-caused before fixing:
  wadesk.in's goal-question flow (`maybeRunGoalFlow()`) only ever fires
  for a phone the CRM already recognises as an open Lead
  (`getCrmLeadContext()` → `Lead::findOpenByPhone()`) — so the irrelevant
  AI reply was a downstream SYMPTOM, not the root cause; the Lead getting
  created in the first place (`WhatsappWebhookController::
  handleUnmatchedNumber()`) was. Confirmed via AskUserQuestion before
  building: fix belongs on the CRM side, at Lead-creation time, not as a
  wadesk.in-side reply suppression that would leave the stray-Lead
  problem recurring on every future case.
  **Immediate cleanup** (production, via the app's own UI, not raw SQL):
  added Sohamm (917744939530) as a new contact under Exim Internationals
  (client #138), logged a note there explaining the merge, and closed
  Lead #452 as Lost with a note pointing back to the client — confirmed
  the AI Next Action job (`AnalyzeLeadNextAction`, 2026-09-18 entry above)
  correctly re-analyzed the closed lead as "Existing client contact
  misclassified as lead; close as duplicate/misfile" afterward.
  **Systemic fix**: new `Customer::findMentionedInText()` — checks the
  message text (or, for a document, its filename; wadesk.in sends that as
  the message body when there's no caption) for a FULL, normalized match
  against an existing Client's company name, called from
  `handleUnmatchedNumber()` right before `Lead::create()`. Deliberately
  conservative: requires the whole normalized name as a substring (not
  just one word), with a minimum length floor, so a short/generic client
  name (e.g. "SEO") can't match almost anything — this is a "maybe, ask a
  human" signal, never treated as certain the way the existing
  phone-based `findByPhone()` check is (2026-09-03 entry, archived). On a
  match, the message is logged to that Client's own timeline instead
  (`recordPossibleClientMessage()`) and Admin/Manager are notified
  (`PossibleClientMessageNotification`) to confirm or correct it. No Lead
  created on a match means wadesk.in's goal-flow naturally never fires
  either — one CRM-side fix closes both the stray-Lead problem and the
  irrelevant-AI-reply problem, no wadesk.in change needed.
  6 new Pest tests in `WhatsappWebhookTest.php` (filename match, free-text
  match, short-name-no-match, no-mention-no-match, Admin/Manager
  notification sent/not-sent by role+active), full `tests/Feature/Clients`
  + `tests/Feature/Leads` + `tests/Feature/Api` suites re-run: 767 passed,
  no regressions. Pint clean. No migration. PR #212 merged (`a6db806`) and
  deployed via the standard SSH `git pull` — no migration needed, config/
  route/view caches rebuilt, verified live (`/login` redirects to a
  working dashboard, `git log` on the server matches the merge commit).
  See [[whatsapp-lead-goal-reask-and-duplicates]] for the 2026-09-18
  precedent this follows (a different root cause — two phone numbers per
  Meta lead — but the same "fix wadesk.in's symptom vs. the CRM's root
  cause" judgment call).
- **2026-09-23 — Force Lead Assignment: a company-wide on/off switch that
  routes every new lead to one chosen Sales rep, unconditionally.** Owner
  asked (2026-09-22) "can I allot all new leads to one Sales person as an
  admin?" Investigated first: the existing `LeadAssignmentRule` mechanism
  only matches on exactly one of `utm_campaign`/`service_id`/`va_paid`
  (XOR) — there's no catch-all rule type, so a plain WhatsApp inbound or
  manual-entry lead (no campaign, no service tag) always falls through to
  the least-loaded round-robin regardless of how many rules exist. Gave 3
  options via AskUserQuestion (a rule per active service — usable today
  but not a true 100% guarantee; temporarily deactivating other Sales
  reps — blunt, also kills their login/visibility elsewhere; or a proper
  toggle, same shape as `NextActionSetting`'s 2026-09-17 pause switch) —
  owner picked the proper toggle.
  New `LeadAssignmentSetting` singleton (`current()`, same `firstOrCreate`
  pattern as `NextActionSetting`/`BillingSetting`) holding `enabled` +
  `forced_user_id` + `updated_by`. `LeadObserver::autoAssign()` now checks
  `LeadAssignmentSetting::current()->eligibleForcedUser()` FIRST, ahead of
  `resolveRuleAssignee()` (the existing campaign/service rule match) and
  the least-loaded round-robin fallback — when the switch is on, it wins
  regardless of any matching rule. `eligibleForcedUser()` re-checks the
  forced target is still an active Sales user at match time, same
  "re-check, don't trust a stale FK" guard `LeadAssignmentRule::
  eligibleAssignee()` already uses — a switch left on against a
  since-deactivated or role-changed rep falls through to the normal
  rule/round-robin path instead of silently assigning to someone
  ineligible. Never reassigns a lead that already has an owner (same
  `owner_id !== null` early-return `autoAssign()` already had).
  New `LeadAssignmentSettingController` (index/enable/disable — two
  explicit actions rather than one toggle, so a double-submit can't flip
  it twice unnoticed, mirroring `NextActionSettingController`'s own
  pause/resume shape exactly), gated by `menu.access:lead-assignment-
  settings`, no dedicated Policy class (same no-Policy convention as
  Billing Settings/Notification Settings/Lead Assignment Rules). New
  "Force Lead Assignment" AdminConfig menu item (`roles =>
  [UserRole::Manager]`, Admin implicit), placed right after Lead
  Assignment Rules. `LeadAssignmentSettingRequest` validates
  `forced_user_id` is a real, active, Sales-role user (`Rule::exists()`
  scoped the same way `LeadAssignmentRuleRequest` already validates
  `assigned_user_id`).
  12 new Pest tests (`LeadAssignmentSettingsTest` — access control for
  both Admin and Manager, default-disabled, enable/disable + `updated_by`
  recorded, rejects a non-Sales/inactive target, forces a lead even when
  a matching `LeadAssignmentRule` exists — proving priority order — forces
  a lead with no matching rule at all, falls back correctly once disabled
  and once the forced target is deactivated, never reassigns an
  already-owned lead), full `tests/Feature/Leads` suite re-verified (469
  passed, no regressions) plus the existing `LeadAssignmentRuleTest`/
  `NextActionSettingsTest`/`MenuAccessTest` suites (28 passed) — the new
  first-checked branch in `autoAssign()` couldn't have silently changed
  any of that existing rule-matching behavior, verified rather than
  assumed. Pint clean. One new migration (`lead_assignment_settings`
  table), migrated clean against local MySQL. `admin.md` extended with a
  new "16a-i. Force Lead Assignment" section, same placement pattern as
  the "16a. Lead Assignment Rules" section it sits beside — `manager.md`
  deliberately left untouched, since Lead Assignment Rules was never
  documented there either (checked before assuming a second guide needed
  updating). **Merged + deployed same day** (PR #213, `8b66e1a`): SSH
  `git pull` + `migrate --force` + `MenuItemsSeeder` re-seed + route/view
  cache rebuild, verified live (`/login` 200, new route 302s logged-out,
  table/menu row exist, `LeadAssignmentSetting::current()->enabled`
  confirmed `false` by default so the deploy itself changed nothing about
  live lead routing, log clean). Switch is off by default — owner must
  turn it on with a chosen rep to actually activate it.
- **2026-09-23 (same day) — closed a real backlog item: the two
  Visibility Audit EMAIL jobs (`SendVisibilityAuditFirstInviteEmailJob`/
  `SendVisibilityAuditRecoveryNudgeEmailJob`) still called the narrower
  `Lead::hasStaffWhatsappReplySince()` instead of the broader
  `Lead::hasStaffEngagementSince()`, a known inconsistency flagged (but
  not fixed) in the 2026-09-17 `SendLeadWelcomeFollowUps` entry above.**
  Every WhatsApp-side sibling job (`SendVisibilityAuditFirstInviteJob`,
  `SendVisibilityAuditRecoveryNudgeJob`) was already fixed to the broader
  check back on 2026-09-13 (lead #322 incident) — these two email jobs
  were the last stragglers, meaning a lead staff had already engaged by
  phone alone (a logged call, no WhatsApp reply, no note) could still
  receive a cold automated VA first-invite or recovery-nudge EMAIL on top
  of a live conversation. One-line fix in each job:
  `hasStaffWhatsappReplySince()` → `hasStaffEngagementSince()`, same
  argument (the lead's `created_at` for the first-invite email, the
  funnel event's `created_at` for the recovery-nudge email) — no other
  logic changed.
  2 new Pest tests (one per job, `VisibilityAuditFirstInviteTest`/
  `VisibilityAuditRecoveryNudgeTest`) — each proves the actual gap by
  creating a real `CallLog` with no WhatsApp-tagged note at all and
  asserting the email is still suppressed; the pre-existing "skips when
  staff replied over WhatsApp" tests would have passed even under the old
  code, so these are the ones that would have caught the regression.
  Full `tests/Feature/Integration` suite re-verified (469 passed, no
  regressions) plus `tests/Feature/GenerateLeadRecommendationTest.php` +
  `tests/Feature/Ai` (228 passed) since both jobs are reachable from
  `GenerateLeadRecommendation::handle()`'s own dispatch paths. Pint
  clean. No migration, no route/config/menu change.
- **2026-09-23 (same day) — real gap, reported live: leads and clients
  were not searchable by mobile number at all, on either the Lead
  Generation list or the Clients list.** Owner: "the leads are not
  searchable through mobile number on the lead page or client page."
  Root cause: `LeadController::filteredLeads()` only ever searched
  `name`/`company`/`email`, `CustomerController::filteredCustomers()`
  only ever searched `company_name`/`email`/`gstin` — `phone` was never
  in either filter at all. The global top-nav search's Lead section had
  the same gap; its Client section checked `phone` but not
  `alternate_phone`.
  **A naive plain `orWhere('phone', 'like', ...)` was NOT enough** — the
  first attempt at this fix, tested against a realistically-formatted
  phone (`"+91 98765 43210"`), failed outright: a user searching bare
  digits (the common case) doesn't substring-match a value stored with
  spaces/a country-code prefix. Real fix: new `Phone::searchDigits()`/
  `Phone::normalizedSql()` on the existing `App\Support\Phone` helper —
  strips space/`+`/`-`/parens from both the stored column (via a SQL
  `REPLACE()` chain) and the search term before comparing, reusing this
  app's existing phone-normalization convention (`Phone::digits()`/
  `last10()`) rather than inventing a new one. Only applies when the
  search term itself has ≥4 digits, so a stray digit inside an unrelated
  text search doesn't widen results unexpectedly.
  9 new/updated Pest tests (`LeadSearchTest`, `ClientSearchTest`,
  extended `GlobalSearchTest`), full `tests/Feature/Leads`+`Clients`
  suites re-verified (678 passed, no regressions), Pint clean.
  Live-tested against real local MySQL before merge (existing fixtures
  plus a real formatted `SMOKETEST` lead). PR #215 merged (`d1deaed`),
  deployed via plain SSH `git pull` (no migration) + OPcache touch.
  **Verified live 3 ways on real production data, via a real
  authenticated Chrome session (owner was already logged in)**: Lead
  list (`/leads?search=9421760797` → found "Sanjay Gupta," phone stored
  as `94217 60797` with a space — the exact real-world formatting
  mismatch), Client list (`/clients?search=9167547808` → found
  "Tathastu"), and the global top-nav search (`/search?q=9421760797` →
  correctly found both the Client "Simran Enterprises" and the Lead
  "Sanjay Gupta" sharing that number). No production credentials/2FA
  were available in-session for a fresh login — used the owner's own
  already-authenticated Chrome session instead once they confirmed it.
- **2026-09-23 (same day) — closed a backlog item flagged 2026-09-12 but
  never fixed: `VisibilityAuditFunnelTrackingController::enter()` was
  hardcoded to always redirect to the GBP offer landing page regardless
  of a lead's actual resolved recommendation.** A leftover from before
  the funnel was unified across all 4 offers (Milestone 13). Milestone
  18's `find-my-recommendation` page already deliberately bypasses this
  by routing non-GBP leads directly to their own recommendation URL —
  but `enter()` itself was never fixed, so anything else that links to
  it (a stale recovery email/WhatsApp link, or a future channel) would
  still send a non-GBP lead to the wrong offer page.
  Fixed with a plain read of the lead's already-resolved
  `recommendation_offer_key` column — deliberately NOT
  `GenerateLeadRecommendation::handle()`, since that action can dispatch
  a real first-touch WhatsApp/email send as a side effect the moment a
  recommendation genuinely changes, which a page VISIT must never
  trigger. New private `nonGbpRecommendationUrl()` returns null (meaning
  "use the normal GBP landing page") unless the lead's own already-
  resolved recommendation points somewhere else. The funnel-tracking
  event (`LandingViewed`) and AI re-score still fire exactly as before
  regardless of which page the visitor ends up on — only the final
  redirect target changes.
  3 new Pest tests (non-GBP recommendation redirects to its own URL, GBP
  recommendation still redirects to the GBP page, no-recommendation-yet
  still redirects to the GBP page — the unchanged default), full
  `VisibilityAuditFunnelTrackingTest` suite (14 passed) plus a broader
  regression sweep across `tests/Feature/Integration` +
  `FindMyRecommendationTest` + `GenerateLeadRecommendationTest` (491
  passed, no regressions), Pint clean. Live-tested against real local
  MySQL before merge (a real `SMOKETEST` lead with a non-GBP resolved
  recommendation). PR #216 merged (`4bdda31`), deployed via plain SSH
  `git pull` (no migration) + OPcache touch. **Verified live on real
  production data**: lead #457 (a real resolved `lead_generation_audit`
  recommendation) — `GET /offers/visibility-audit/enter?lead=457`
  correctly redirected to `/offers/recommendation/{its own token}`
  instead of the old hardcoded GBP page; the anonymous/no-lead case
  confirmed still redirects to the GBP page unchanged. The 2 real
  `VisibilityAuditFunnelEvent` rows this verification created (not
  genuine visitor activity) were cleaned up afterward; a resulting
  `ScoreLead` AI re-score for lead #457 was left alone — a legitimate
  side effect of hitting a real endpoint, nothing to undo.
  This closes out the full backlog sweep from this session — PRs #213,
  #214, #215, and #216 all merged, deployed, and verified live same day.
  Two items remain deliberately deferred to next session (a Meta lead
  `name`-backfill decision, and a per-campaign funnel generator design
  discussion) — owner explicitly said to keep those for next time.
- **2026-09-23 (later) — Two-way contact-name sync between the CRM and
  wadesk.in; wadesk.in no longer overwrites an edited name.** Team-
  reported: a Contact renamed in wadesk.in reverted to the original name
  the moment that person messaged again, and the CRM never saw the edit
  either, so one person carried two different names across the two apps.
  Root-caused, three separate gaps: (1) wadesk.in's webhook overwrote
  `Contact.name` with the WhatsApp profile name on EVERY inbound message/
  call; (2) the CRM only ever pushed a Lead's name to wadesk.in on create/
  reassign (`SyncLeadToWadeskJob`), never on a rename; (3) a wadesk.in
  rename never reached the CRM at all. Confirmed via AskUserQuestion: sync
  **both ways** (latest edit wins, not CRM-as-master), covering **Leads
  AND Client contact persons**.
  **wadesk.in**: new `findOrCreateContact()` in the webhook — profile name
  only fills a NULL name, never replaces one (both message + call paths);
  `notifyCrm()`'s `contact_name` now sends the saved name, not the raw
  profile name. New `POST /api/contacts/sync-name` (`lead-sync` service-key
  scope, update-only, matches by last 10 digits, explicit NULL-name branch
  since SQL `name != x` alone skips NULLs; added to `middleware.ts`'s
  exempt list or the CRM's call would have been bounced to /login). A
  manual rename in `PATCH /api/contacts/[id]` fires the new fire-and-forget
  `notifyCrmContactName()` (`CRM_CONTACT_NAME_URL`, passed through
  `docker-compose.yml` per the per-app env gotcha).
  **CRM**: new `SyncContactNameToWadeskJob`, dispatched from
  `LeadObserver::updated()` (phone + alternate_phone) and a new
  `Contact::booted()` hook. New `WadeskContactNameController` at
  `POST /api/webhooks/wadesk/contact-name` (same Bearer token as every
  other wadesk.in bridge) renames every matching OPEN Lead and every Client
  Contact — never `Customer.company_name` — using `Phone::normalizedSql()`,
  NOT `Lead::findOpenByPhone()` (its raw LIKE missed a formatted
  `"+91 98765 43210"` in the first test run). Echo loop closed on both
  sides: the CRM applies an inbound rename inside
  `SyncContactNameToWadeskJob::withoutPushing()`, and wadesk.in's sync-name
  route never notifies back. The "WhatsApp Inquiry" placeholder became
  `Lead::PLACEHOLDER_NAME` and is never pushed as a name, including by
  `SyncLeadToWadeskJob` (which previously could overwrite a real wadesk.in
  profile name with it).
  **Real bug caught by the local smoke test, not the first test run**:
  `LeadObserver::created()`'s nested `autoAssign()` save makes EVERY
  attribute read as `wasChanged()` (original not yet synced — same class as
  the 2026-09-18 `ai_detected_next_action` `isDirty()` bug), so every new
  lead queued a bogus "rename" push. Guarded with
  `filled($lead->getOriginal('name'))`; a regression test that actually
  exercises the autoAssign path was confirmed to fail without the guard.
  17 new Pest tests (`WadeskContactNameSyncTest`), Leads/Integration/
  Clients/Api/Portal/Livewire/Deals suites green (only the known
  `MeetingRequestTest` IST flake), Pint clean; wadesk.in `tsc --noEmit` +
  eslint clean (no test suite there). Smoke-tested end to end locally with
  both apps running: wadesk→CRM rename, CRM rename → real queue worker →
  wadesk.in contact renamed, an unsigned simulated inbound message from the
  renamed contact kept the edited name, and a brand-new sender still got
  its profile name; smoke data removed from both local DBs. No migration.
  Deploy needs `CRM_CONTACT_NAME_URL=https://crm.niranjanenterprises.co.in/api/webhooks/wadesk/contact-name`
  set in wadesk.in's prod `.env` (owner-run, no SSH to that VPS).
  `sales.md`/`telecaller.md` updated, their PDFs regenerated.
- **2026-09-23 (later still) — Two wadesk.in-bridge bugs found while
  auditing unassigned wadesk.in chats: 10-digit phones sent without a
  country code, and leave-cover syncs silently rate-limited.** (1) Lead
  #326 was stored as `8529857994`; every CRM → wadesk.in call sent
  `Phone::digits()` as-is, so `/api/leads/sync` created an EMPTY duplicate
  contact/chat under the 10-digit number, assigned the reps there, and
  left the real `918529857994` chat unassigned (17 wadesk.in chats have
  10-digit contacts, 7 are such duplicates). New `Phone::forWhatsapp()`
  (bare 10 digits or a leading trunk `0` → `91…`, foreign numbers
  untouched) now used by all 13 jobs that send a phone to wadesk.in.
  `SyncContactNameToWadeskJob` deliberately keeps `digits()` — wadesk.in
  matches it on last 10. Read-only production check before fixing: only
  12 leads are stored 10-digit and NONE had ever been sent an automated
  WhatsApp message, so no missed sends to recover. (2) Same audit, 14 days
  of logs: `SyncLeaveCoverToWadeskJob` got 407 × 401 — wadesk.in's
  `service-key.ts` answers 401 (not 429) past 30 calls/min on
  `/api/leads/set-cover`, and `LeaveCoverage::dispatchSync()` queued one
  job per open lead at once (Kiran Katte on leave today, ~120 leads → 90
  rejected on EVERY 30-minute run, so covering rep Mohit Patil couldn't
  see most of her chats; Mohit's own 25–26 Sep leave hit it on approval).
  Fixed by staggering dispatch 25/minute (`WADESK_COVER_SYNCS_PER_MINUTE`)
  and releasing the job for a 60s retry on 401/429 instead of logging and
  giving up. Also noted, not a live issue: ~2,240 "Template not found" /
  "#132000 parameter mismatch" rejections were all on 2026-09-12/13 while
  the offer templates were awaiting Meta approval — none since.
  Lead #326's real chat was assigned to Mohit/Rohit by hand in wadesk.in.
  11 new tests (`PhoneTest`, 3 `LeaveCoverageTest`, 1
  `WadeskContactNameSyncTest`); Integration/Leads/Api/Clients/Sales/Unit/
  Billing + offer/funnel suites green; Pint clean. No migration.
