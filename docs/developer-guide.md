# Developer Guide — NEDS CRM

This is the technical reference for anyone writing code in this repository —
you in six months, a new hire, or a contractor. For product/business context
(what each module does, GST rules, why certain decisions were made), see
[`CLAUDE.md`](../CLAUDE.md) at the repo root — it's kept as the running
source of truth for conventions and architectural decisions, and this guide
assumes you've skimmed it. For end-user instructions, see
[`docs/user-guides/`](user-guides/).

---

## 1. What this is, in one paragraph

A custom Laravel 12 CRM for Niranjan Enterprises Digital Solutions (NEDS), a
Pune-based digital agency — leads through GST-compliant invoices, recurring
retainer billing, projects/tasks, a support desk, a client portal, and a set
of opt-in AI features (Anthropic Claude). It deploys to plain PHP/MySQL
shared hosting (Hostinger) on purpose — see §8 for why that constrains
every dependency choice.

## 2. Local environment setup

**Prerequisites:** PHP 8.2+, Composer, Node 18+, MySQL 8 (or just use SQLite
for quick local work — see below).

```bash
git clone https://github.com/niteshmorepune/neds-crm
cd neds-crm
composer setup      # composer install + .env + key:generate + migrate + npm install + npm build
```

`composer setup` (defined in `composer.json`) does the above in one shot. If
you'd rather step through it manually, or need to point `.env` at a real
MySQL database instead of the SQLite default:

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env — DB_CONNECTION, DB_DATABASE, etc. if not using SQLite
php artisan migrate
npm install && npm run build
```

**Seed some data to actually click around:**
```bash
php artisan db:seed --class=ServicesSeeder
php artisan db:seed --class=MenuItemsSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=FestivalsSeeder      # optional
php artisan db:seed --class=DemoDataSeeder       # optional — fake leads/deals/invoices for UI testing
```
`AdminUserSeeder` creates `niranjan.enterprisespune@gmail.com` / `password` —
change it if this ever points at anything real. **Never re-run
`DemoDataSeeder` against production** — see the "Do NOT" list in
`CLAUDE.md`.

**Run the app:**
```bash
composer dev
```
This runs `php artisan serve` + `queue:listen` + `php artisan pail` (log
tailing) + `npm run dev` (Vite) concurrently — one command, one terminal,
matches `CLAUDE.md`'s documented `composer dev` command.

**AI features are off by default locally.** Set `AI_ENABLED=true` and a real
`ANTHROPIC_API_KEY` in `.env` to exercise them; otherwise every AI call
fails silently and the app behaves exactly as if AI were disabled (this is
deliberate — see §7).

## 3. Running tests

```bash
php -d memory_limit=512M vendor/bin/pest
```

(1039 tests, ~2 minutes.) **Use `vendor/bin/pest` directly, not
`php artisan test`** — a `memory_limit` setting that matters for a couple
of PDF-generation-heavy tests (dompdf) doesn't propagate through
`artisan test`'s child-process wrapper, and you'll get a confusing fatal
(`Allowed memory size of 134217728 bytes exhausted`) instead of a clean
run. The default CLI `memory_limit` (128M on most setups) isn't enough
either — pass `-d memory_limit=512M` (or higher) explicitly.

Tests run against an **in-memory SQLite database** with the queue on `sync`
(see `phpunit.xml`) — completely separate from whatever MySQL your local
dev server points at. Two things this means in practice:
- A migration passing tests may never have actually been run against your
  local dev database. If a feature works in tests but 500s in the browser,
  `php artisan migrate` before assuming something deeper is wrong.
- `QUEUE_CONNECTION=sync` in tests means any queued job (`ScoreLead`,
  `ProvisionClientExternallyJob`, etc.) runs **immediately, synchronously**
  when dispatched — including from model `booted()` hooks. Production uses
  the `database` driver with a cron-triggered worker, so the same code path
  is genuinely async there. Don't be surprised when a test fires an AI call
  or webhook synchronously that would be deferred in production.

**134 test files** under `tests/Feature/`, organized by feature area
(`Leads/`, `Deals/`, `Billing/`, `ClientRadar/`, `Ai/`, `Integration/`,
`Security/`, `Regression/`, etc.) — mirror that structure for new tests
rather than inventing a new grouping. Critical paths that must stay covered
per `CLAUDE.md`: GST calculation, invoice numbering, deal stage transitions,
role permissions.

## 4. Architecture tour

Standard Laravel structure. A few directories carry real weight:

| Directory | What lives there |
|---|---|
| `app/Models/` (39 models) | Eloquent models. Fat models are fine at this scale. |
| `app/Actions/` | Extracted business logic once a model/controller method would exceed ~50 lines — e.g. `ConvertLead`, `ConvertQuotationToInvoice`, `GenerateMilestoneInvoice`. |
| `app/Services/` | Stateless-ish services doing one job well: `GstCalculator`, `InvoiceNumberGenerator`, `MenuResolver`, `SlaCalculator`, `SalesPipelineMetrics`, `AiAssistant` + `AnthropicClient`, and the various `*Metrics` report services. New report logic belongs here, not in a controller. |
| `app/Policies/` | One per authorizable model (`LeadPolicy`, `DealPolicy`, `InvoicePolicy`, …) — the **actual** access-control layer. See §5. |
| `app/Enums/` | Backed PHP enums for every status/type field (`DealStage`, `InvoiceStatus`, `LeadSource`, `UserRole`, …) — always add a `label()` method for the human-readable string, never inline a status string in a Blade file. |
| `app/Livewire/` | Full-page and embedded Livewire 3 components — the Deals Kanban board, Quotation/Invoice builders, `AskTheCrm`, `TicketTriageSuggestion`, `ClientRadarSuggestion`, etc. No SPA, no separate frontend build beyond this. |
| `app/Jobs/` | Queued work: AI drafting jobs (`ScoreLead`, `DraftLeadNurtureFollowUp`, `DraftMonthlyWinsNote`, …), external provisioning (`ProvisionClientExternallyJob`), inbound processing (`ImportMetaLead`). |
| `app/Console/Commands/` | Scheduled commands (digests, reminders, `DispatchScheduledTasks`, `GenerateRecurringInvoices`, `BackupDatabase`) — see §6. |
| `app/Http/Middleware/` | Auth/webhook-signature guards: `EnsureMenuAccess` (menu-key route gate), `RequireTwoFactor`, and one `Verify*` middleware per inbound webhook (`VerifyDrishtiWebhookSignature`, `VerifyMetaWebhookSignature`, `VerifyWhatsappWebhookToken`, …). |
| `routes/web.php` / `routes/api.php` | Internal app + Client Portal routes; API webhooks (Drishti, SMDost, WhatsApp, Meta Lead Ads, biometric ADMS, lead capture). See [`docs/sitemap.html`](sitemap.html) for the full page-by-page map. |

**Frontend:** Blade + Livewire 3 + Alpine.js + Tailwind CSS (v3, not v4 —
the `@tailwindcss/vite` v4 dependency in `package.json` is present but
unused; don't reach for v4 syntax). No React, no Inertia, no separate SPA —
deliberate, keeps the Hostinger deploy simple (see §8).

## 5. Authorization: Policies are real, the Menu Controller is not

Two separate systems, easy to conflate:

- **Policies** (`app/Policies/`), checked via `$this->authorize(...)` in
  controllers and `@can` in Blade, are the **actual** access control.
  Six roles: `admin`, `manager`, `sales`, `support`, `accounts`, `intern` —
  plus **additional roles** via a `role_user` pivot (a user has one primary
  role driving their dashboard panel and auto-assignment/routing, plus any
  number of additional roles that expand Policy checks, notifications, and
  sidebar access). `User::hasRole(...$roles)` checks both the primary role
  and the pivot — write new role checks against `hasRole()`, never
  `$user->role === X` directly, or a user's additional roles silently won't
  apply.
- **Menu Controller** (`menu_items` + `menu_item_role` + `menu_item_user`
  tables, resolved by `App\Services\MenuResolver`) only controls **sidebar
  visibility**. It is cosmetic. Hiding a menu item does **not** block the
  route — that's still the Policy's job. Never rely on menu visibility as a
  security boundary.

`MenuResolver` caches per-user, invalidated by a single global version
counter rather than per-key deletion (works on the plain database/file
cache stores shared hosting gives you — no cache tags needed). Editing a
user's roles via the Users screen, or editing Menu Controller settings,
already calls `MenuResolver::flush()` for you. The gotcha is only if you
change a user's role(s) or `menu_items` **outside those flows** — directly
via `tinker`, a one-off script, or a seeder — call
`app(MenuResolver::class)->flush()` yourself afterward, or you'll be
debugging a sidebar that "should have updated" but didn't.

## 6. Scheduled jobs, queue, and cron

Everything time-based funnels through Laravel's scheduler, defined in
`routes/console.php` (check `php artisan schedule:list` for the live
picture), triggered by a **single cron entry** running
`php artisan schedule:run` every minute — no
supervisor, no Horizon, no long-running process (shared hosting can't run
those). The `database` queue driver means queued jobs sit in a table until
a worker picks them up; production also runs `queue:work --stop-when-empty`
every minute via the same cron mechanism.

Notable scheduled commands (`app/Console/Commands/`) — group them mentally
by what they do:
- **Digests/reminders:** `SendMorningDigest`, `SendWeeklyOwnerDigest`,
  `SendStagnationAlerts`, `SendFollowUpReminders`,
  `SendPaymentPromiseReminders`, `SendContractRenewalReminders`,
  `SendRecurringInvoiceDueWarnings`, `SendDailyReportReminders`.
- **Generation:** `GenerateRecurringInvoices`, `DispatchScheduledTasks`
  (the per-service maintenance-task templates), `CreateMonthlyBriefs`.
- **AI drafting:** `DraftLeadNurtureFollowUps`, `DraftMonthlyWinsNotes`,
  `DraftProjectDailyUpdates`, `DraftFestivalGreetings`, `SendProjectUpdatesDigest`.
- **Housekeeping:** `MarkOverdueInvoices`, `CleanupOrphanedRecords`,
  `BackupDatabase` (nightly, 2 AM, mysqldump — see
  [`docs/backup-restore.md`](backup-restore.md)).

`DispatchScheduledTasks --date=YYYY-MM-DD` is idempotent and safe to
backfill a missed day by hand.

## 7. AI integration pattern

Every AI feature goes through `App\Services\AiAssistant` (prompt
construction + parsing) calling `App\Services\AnthropicClient` (the actual
HTTP call to Anthropic, via Laravel's HTTP client) — never call the
Anthropic API directly from a controller or job. Rules that every AI method
follows, and any new one should too:

- **Feature-flagged**: `config('services.anthropic.enabled')`
  (`AI_ENABLED` in `.env`). Off by default.
- **Wrapped in try/catch**: an AI failure must never break the surrounding
  workflow — a lead still saves, a ticket still creates, even if the AI
  call throws or times out.
- **Never auto-sends**: every AI output is a draft a human reviews and
  explicitly sends/approves. The only feature that runs without an explicit
  per-instance human trigger is lead scoring (automatic on save) — even
  that only writes a score + reason, never acts.
- **Model**: `claude-haiku-4-5-20251001` by default
  (`config('services.anthropic.model')`), capped around 1000 output tokens
  per call. Don't hardcode a different model in a new feature without a
  reason — check `config/services.php`'s `pricing` array covers whatever
  model you pick, or the AI Usage Report's cost estimate silently falls
  back to `'default'` pricing.
- **Usage tracking**: every call logs to `ai_usages` (feature tag, tokens,
  timestamp) — this is what powers the AI Usage Report. `AiResult`/
  `AiAssistant` expose `$lastUsageId`, set centrally in `trimmed()` for any
  method that routes through it. If you add a method that makes **more
  than one** AI call in sequence (like `AskTheCrm::ask()`'s
  classify-then-narrate pattern), capture `$lastUsageId` right after the
  call whose *output* is actually shown to the user, not the last call
  in the method — nobody should be able to "rate" an internal
  classification step.
- **Rate limiting**: only the one client-triggered feature (the portal
  "Ask about your account" assistant) is rate-limited
  (`AI_PORTAL_ASSISTANT_DAILY_LIMIT`, via `RateLimiter` inside the Livewire
  action, since every Livewire component shares one `/livewire/update`
  endpoint — a route-level `throttle` middleware doesn't fit that shape).

## 8. Money, dates, and GST — non-negotiable conventions

- **Money is always an integer in paise**, never a float. Format for
  display (rupees) at the view layer only. This is load-bearing across
  invoices, quotations, and payments — don't introduce a float anywhere in
  that chain.
- **Dates shown to users are Asia/Kolkata**; the database always stores
  UTC. Convert at the edge, not in the middle of business logic.
- **GST**: `App\Services\GstCalculator` is the single source of truth for
  the CGST+SGST (same state, Maharashtra/27) vs. IGST (different state) vs.
  zero-rated (overseas `Country`) split. Company state is fixed at
  Maharashtra. GSTIN format is validated against the standard 15-character
  regex. Never duplicate this logic inline in a controller or Blade file —
  route through the service.
- **Invoice numbering**: `App\Services\InvoiceNumberGenerator` produces the
  sequential `NEDS/{FY}/{sequence}` format (financial year = April–March).
  Numbers are assigned on demand (the "Pending Invoice #" → "Assign Invoice
  Number" flow), not at invoice-creation time — don't change this without
  understanding why (sequential gaps from deleted draft invoices would
  otherwise be a real GST compliance problem).

## 9. Why Hostinger shared hosting shapes every dependency choice

This app deploys to **PHP/MySQL shared hosting**, not a VPS or container
platform. That's why:
- No Redis, no Horizon, no Reverb/Pusher/websockets, no long-running Node
  process. Cache/session/queue all use `database` or `file` drivers.
- No `Route::get()` broadcast channels or SSE — "real-time-ish" features
  (notifications, digests) poll or run on the minute-cron schedule instead.
- `exec()` is disabled on Hostinger — `php artisan storage:link` fails
  there; the symlink has to be created manually on deploy
  (`ln -s storage/app/public public/storage`).
- Deploys are a plain `git pull` over SSH (see
  [`docs/deploy-checklist.md`](deploy-checklist.md)), not a CI/CD
  pipeline — GitHub Actions was tried and abandoned early on because SSH
  kept timing out from Hostinger's shared environment.

Keep this constraint in mind before adding any package — check shared
hosting compatibility first, per the "Do NOT" list in `CLAUDE.md`.

## 10. Integrations

Three other systems this CRM talks to, all via signed/token-authenticated
webhooks (see `app/Http/Middleware/Verify*.php` and `routes/api.php`):

| System | Direction | What for |
|---|---|---|
| **Drishti** (nedsdrishti.in) | both ways | Service-delivery cockpit — client auto-provisioned on deal Won, event webhooks flow back to the CRM's activity feed. |
| **SMDost** (socialmediadost.com) | both ways | AI content production — brief approval creates a draft invoice; content pieces sync into the CRM's Content Collaboration module. |
| **wadesk.in** | both ways | WhatsApp business number — inbound messages create tickets/leads; staff replies from the CRM send back out through wadesk.in. |
| **Meta Lead Ads** | inbound | Facebook/Instagram lead-form webhook → `ImportMetaLead` job. |
| **eSSL biometric device** | inbound | ADMS push protocol (`/api/iclock/cdata`) syncs attendance punches. |

Full operational detail and troubleshooting: [`docs/user-guides/integrations.md`](user-guides/integrations.md).
Each integration's shared secret is a separate `.env` value — see the
comments in `.env.example`, they explain which side's env var each one has
to match.

## 11. Coding conventions

- PSR-12, enforced via `laravel/pint` (run `vendor/bin/pint` before
  committing if you're not sure your formatting matches).
- Descriptive names, no abbreviations in DB columns.
- **FormRequest classes for all validation** — never inline
  `$request->validate()` in a controller.
- All queries through Eloquent/query builder — never raw SQL with string
  interpolation.
- **Migrations are append-only once merged** — never edit an existing
  migration that's already been merged to `master`; write a new one.
- Soft deletes on `Customer`, `Lead`, `Deal`, `Invoice`, `Ticket` — every
  table gets `created_at`/`updated_at`.
- Activity logging: create/update/delete on core models logs to
  `activities` (who, what, when, changed fields as JSON) — this is what
  the Audit Log page reads from.
- Commits: conventional commits (`feat:`, `fix:`, `chore:`, `docs:`).

## 12. Where the rest of the documentation lives

| Doc | Audience | What's in it |
|---|---|---|
| [`CLAUDE.md`](../CLAUDE.md) | Anyone touching this repo | Product spec, domain model, GST rules, and the **Decisions log** — every "we chose X because Y" call made on this project. Read this first. |
| `docs/developer-guide.md` (this file) | Developers | Architecture, conventions, local setup. |
| [`docs/sitemap.html`](sitemap.html) | Developers/anyone | Every route in the app, mapped by access boundary (role-gated, admin-only, client portal, no-login). Open it directly in a browser. |
| [`docs/user-guides/`](user-guides/) | Internal staff (all roles) | How to actually use the CRM day to day — also rendered live in-app under Help. |
| [`docs/training/`](training/) | Internal staff | Scene-by-scene recording scripts for onboarding videos (PDF handouts in `training/pdf/`). |
| [`docs/marketing/`](marketing/) | Sales/prospects | The sales pitch deck, an external-facing explainer, and vertical one-pagers. |
| [`docs/meta-ads-playbook.md`](meta-ads-playbook.md) | Whoever runs NEDS's own ads | Campaign structure and ad copy for Meta Lead Ads, written to match how the CRM auto-scores an incoming lead. |
| [`docs/deploy-checklist.md`](deploy-checklist.md) | Whoever deploys | Manual SSH deploy sequence. |
| [`docs/backup-restore.md`](backup-restore.md) | Whoever deploys | Nightly backup mechanics and how to restore one. |
| [`docs/troubleshooting.md`](troubleshooting.md) | Anyone debugging | Known issues and how to diagnose common failures. |

## 13. Gotchas worth knowing before you get bitten

- **`php artisan test` vs `vendor/bin/pest`** — see §3. Use Pest directly.
- **Sync queue in tests, database queue in production** — see §3. A test
  proving a job "works" doesn't prove it works async; check the job is
  actually dispatched, not just that its `handle()` logic is correct.
- **`create()` doesn't reflect DB-level defaults** — if a column has a
  database default, an Eloquent `Model::create([...])` without that field
  won't show it until you call `->refresh()`. Bit people before; don't
  assume a freshly-created model's attributes match what's actually in the
  row.
- **OPcache + `config:cache`** — after any `.env` change on the server,
  `php artisan config:cache` is mandatory or the change won't take effect,
  and OPcache can mask a code change's effect on cached config in
  confusing ways. When in doubt after a deploy, `config:clear` then
  `config:cache` fresh.
- **A status enum can hide two real states** — check what actually *sets*
  a status field before assuming its cases tell the whole story (e.g. a
  recurring invoice template being "Ended" vs. "On Hold" look similar in
  the data but mean very different things operationally — see
  `RecurringInvoice::hasEnded()`).
- **Check production data for name/slug drift before seeding** — a service
  or menu label can have been renamed live by the team independent of what
  a seeder assumes; diff against real production data before running a
  seeder that touches existing rows.
