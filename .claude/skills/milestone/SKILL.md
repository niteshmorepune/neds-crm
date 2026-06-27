---
name: milestone
description: Build a NEDS CRM milestone end-to-end. Use when the user asks to start/continue a milestone from BUILD_PLAN.md or to scaffold a module (migrations, enums, models, policies, controllers, Livewire, tests). Encodes this project's conventions and the propose→build→test→smoke→commit→PR workflow.
---

# Building a NEDS CRM milestone

This project ships one milestone per branch/PR (see `BUILD_PLAN.md`). Always read
`CLAUDE.md` first — its rules override defaults. Use **plan mode** for anything
touching money, GST, or permissions.

## Workflow

1. **Branch.** Merge the previous PR, sync `master`, then
   `git checkout -b milestone-N-<name>` off `master`.
2. **Plan first.** Read the milestone in `BUILD_PLAN.md`. Propose an
   implementation plan and surface genuine decisions with AskUserQuestion
   *before* writing code. Wait for approval.
3. **Build** (typical order): migrations → enums (`app/Enums`) → models
   (+factories) → seeders → policies → FormRequests → controllers → Blade →
   Livewire components → routes + sidebar wiring. Then `migrate --seed` against
   MySQL.
4. **Test.** Pest feature tests for every mutating action and the CLAUDE.md
   critical paths, **plus a render test that GETs each new page** (asserting a
   redirect is NOT enough — a stale-route 500 once slipped through). Run
   `php artisan test` (in-memory SQLite) until green, then `vendor/bin/pint --dirty`
   and `npm run build`.
5. **Ship.** The owner's rhythm: smoke-test the live app, then commit + push +
   open a PR. They merge before the next milestone.

## Project conventions (must follow)

- **Stack:** Laravel 12, PHP 8.2+, **Livewire pinned `^3`** (Composer defaults to
  v4 — don't let it), Blade + Tailwind + Alpine, Pest. No Redis/Node servers
  (Hostinger shared hosting; queue/cache/session = `database`).
- **Money:** integer **paise** in the DB; forms take rupees → convert with
  `App\Support\Money` (`toPaise`, and `format()` for Indian ₹). Never floats.
- **Dates:** store UTC, display `Asia/Kolkata`.
- **Auth:** a Policy per model. Pattern so far — sales see own + unassigned;
  admin/manager see all; support/accounts vary per module. Keep a model
  `scopeVisibleTo($user)` in sync with the policy's `view`.
- **Validation:** FormRequest classes, never inline in controllers.
- **Migrations are append-only** once merged; date-prefix new ones after existing.
- **Activity log:** add the `LogsActivity` trait to core models.
- **Soft deletes:** Customer, Lead, Deal, Invoice, Ticket.
- **GSTIN:** validate with `App\Rules\Gstin`. State codes in `config/india.php`
  (Maharashtra = 27 drives intra/inter-state GST).
- **Notes:** reuse the generic `RecordNotes` Livewire component (polymorphic
  `notes()` morphMany). On project pages pass `showPortalToggle=true` — this
  shows a "Share with client" checkbox and defaults it ON so updates reach the
  portal. `notes` table has a `visible_to_client` boolean; portal queries filter
  to `where('visible_to_client', true)`.
- **Customer status:** `CustomerStatus` enum = Active / Prospect / Inactive.
  Lead conversion creates Prospect; Deal Won promotes Prospect→Active via
  `Deal::booted()` updated hook. Clients index defaults to Active filter.

## Sidebar / route gating (read carefully)

The sidebar is data-driven from `menu_items` + role/user pivots, rendered via
`MenuResolver` and cached per user. Route security is the `menu.access:<key>`
middleware (role-based), **independent of sidebar visibility** — never rely on
hiding a menu for security.

When a milestone turns a stub route into a real module:
- Point the resource routes at `/clients`-style URLs but **keep the menu key**
  (e.g. key `customer`, `lead-generation`); apply `menu.access:<key>` to the group.
- Update that key's `route` in `MenuItemsSeeder` and **re-seed**.
- `MenuResolver::flush()` is required to bust the cache (it bumps a version via
  `Cache::forever`; `Cache::increment` no-ops on the DB cache store).
- Fix any hardcoded `/old-path` URLs in `tests/Feature/MenuAccessTest.php`.

## Local environment

MySQL `neds_crm` / user `neds_crm` / `DB_PASSWORD="NedsApp#2026"` (quote it — `#`).
`mysql` CLI: `C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe`. gh is
installed but not logged in — pass a token per-command from the git credential
store. Smoke-test by running `php artisan serve` and driving HTTP with a cookie
jar (scrape the `_token` from the form). Commit only when the user asks;
`.env` is gitignored.

## Maintenance / post-launch (the app is LIVE)

M0–M7 are built, merged, and **deployed live** at https://crm.talktonitesh.com
(Hostinger; details + one-time bootstrap in `docs/deploy-checklist.md`,
`docs/deploy-github-actions.md`, and the [[deployment]] memory). The build phase
is done; new work is maintenance.

- **Deploy is MANUAL, not CI/CD.** `.github/workflows/deploy.yml` was deleted
  2026-06-13 (Hostinger SSH kept timing out). After pushing to `master`:
  `ssh -i ~/.ssh/hostinger_deploy -p 65002 u314035009@89.117.188.107` (key is on
  the local dev box), then `cd /home/u314035009/neds-crm && git pull`. Run
  `php artisan view:clear && php artisan view:cache`; also `route:clear` +
  `route:cache` if routes changed, `migrate --force` if there are new
  migrations, `config:cache` after `.env` edits, and the `MenuItemsSeeder`
  re-seed (below) after menu changes. `public/build` is committed, so the
  `git pull` carries the compiled assets — no separate upload step needed.
  Verify live with `curl` (e.g. `/login` → 200, `/build/manifest.json` shows the
  new asset hash).
- **Per-change loop (minus the milestone plan):** branch or commit on `master` →
  build → `php artisan test` + `pint` → push → manual deploy (above) →
  smoke-test live.
- **Live smoke-testing via curl (no browser, no SSH writes to prod):** drive the
  real app over HTTP with a cookie jar, never `php artisan tinker` against
  production data (the permission system will — correctly — block raw DB
  writes that bypass policies/validation). Steps: fetch `/login`, scrape
  `_token`, POST credentials; if 2FA is enforced you'll land on
  `/two-factor/challenge` — get a fresh TOTP code from the owner and POST it
  immediately (it expires in ~30s). For **Livewire full-page components**
  (`QuotationBuilder`, `InvoiceBuilder`, etc.) there's no plain form to POST:
  parse the `wire:snapshot="…"` attribute out of the rendered HTML (it's
  HTML-entity-encoded JSON), then POST to `/livewire/update` with
  `{_token, components: [{snapshot, updates: {...}, calls: [{method:"save",...}]}]}`
  and header `X-Livewire: true`. The response's `effects.redirect` confirms
  success. Label any throwaway record `SMOKETEST …` and delete it through the
  app's own destroy routes when done — and note that **not every model has
  one** (Tickets and Deals currently don't), so plan cleanup before creating.
  Treat anything that writes to a real financial ledger (recording a payment)
  or flips a real role's production access (Menu Controller role toggles) as
  out of bounds for a "create + delete" smoke test — fall back to the Pest
  suite for those paths and say so explicitly in the report.
- **`public/build` is committed/tracked** (un-ignored) so assets ship via git.
  Adding **uncommon Tailwind utilities** on a new page? `npm run build` locally
  and commit the fresh build — a class used only on one new page can be missing
  from the shipped CSS (prefer app-wide classes like `flex-1` over `col-span-*`).
  See the asset gotcha in [[deployment]].
- **Menu changes need a server re-seed:** deploy runs `migrate` but NOT seeders.
  After editing `MenuItemsSeeder`, run once on the server:
  `php artisan db:seed --class=MenuItemsSeeder --force`.
- **Editing `.env` on the server requires `php artisan config:cache`** (config is
  cached). Hostinger disables `exec()` (so `storage:link` is a manual `ln -s`),
  but `proc_open`/`mysqldump` work.
- **OPcache serves stale PHP bytecode after `git pull`.** If a class change
  doesn't behave as expected on production, OPcache may be serving the old
  version. Confirm with `\Log::error('[tag] v2 running')`. Fix: `touch` the
  changed file to prompt OPcache revalidation. **Config-file exception:** if the
  stale file is a `config/*.php`, `touch` + `config:cache` is NOT enough —
  `config:cache` itself `require`s the file and gets the stale bytecode. Bypass:
  set the value in server `.env` so `env()` resolves it directly, then re-run
  `config:cache`. See [[feedback-gotchas]] for details.
- **Help & user guides:** single source = `docs/user-guides/*.md` → in-app
  `/help` (HelpController via league/commonmark) AND PDF handouts
  (`npm run handouts`, headless Chrome — no Playwright dep). Edit the .md once.
  Guides: getting-started, sales, support, accounts, manager, admin,
  client-portal. Always update the relevant guide after any feature change.
- **Staff accounts:** public registration is disabled; admins add users via the
  **Users** screen. Service lines via **Services** (was the Categories stub).
- **Client portal layout:** `portal-app-layout.blade.php` uses a fixed left
  sidebar on desktop (`lg:w-64`, `lg:pl-64`) and a hamburger Alpine.js overlay
  on mobile. The `@php` setup block (nav links, contact initials) MUST be at the
  very top after `@props` — never inside HTML elements (Blade scoping bug).
  Accepts `header` (page H1 + browser title) and `title` (browser title only).
- **WhatsApp integration:** `tickets.channel` (default 'web') +
  `tickets.whatsapp_conversation_id` (unique). Inbound webhook:
  `POST /api/webhook/whatsapp` → `WhatsappWebhookController`, gated by
  `VerifyWhatsappWebhookToken` middleware (Bearer token from
  `services.whatsapp_webhook.token`). Phone lookup: exact → `+`-prefixed →
  last-10-digit LIKE. wadesk.in fires a fire-and-forget fetch on new/reopened
  conversations. Tier 3 (CRM reply → WhatsApp) is backlogged.
- **Overseas / zero-rated GST:** `Customer::isOverseas()` returns true when
  `country` is set and ≠ 'India'. `GstCalculator::calculate()` has a 4th param
  `$isOverseas = false` — when true, all tax is zero regardless of state code.
  `HasGstTotals` concern and `InvoiceBuilder`/`QuotationBuilder` both pass this.
  PDF shows "INVOICE" (not "TAX INVOICE") and zero-rated supply note.
- **RecordNotes component:** has two access flags — `$canManage` (full CRUD) and
  `$canAddNotes` (note-only, no edit/delete). Pass `canAddNotes=true` when a
  role should post notes but not edit the record (e.g. Support assigned to a
  project). Gate with `abort_unless($this->canManage || $this->canAddNotes, 403)`
  in `addNote()`. `showPortalToggle=true` on project pages defaults new notes to
  `visible_to_client=true`.
- **CustomerPolicy::manage()** controls Add/Edit/Delete contacts on the client
  profile — separate from `update()` (which controls editing client record itself).
  Restricted to Admin, Manager, Sales (own clients). Support can view but not mutate.
- **project_user pivot has `role` column** ('lead' or 'member'). Use
  `->withPivot('role')` when eager-loading assignees. Lead = primary contact;
  `assignees->firstWhere('pivot.role', 'lead') ?? assignees->first()` to get
  the lead assignee for auto-assignment (tickets, reports).
- **Portal ticket auto-routing:** `Portal\TicketController::store()` resolves
  `service_id` and `assignee_id` from the selected project's lead assignee.
  The create view shows a project dropdown only when `$projects->isNotEmpty()`.

## Cross-app integrations (CRM ↔ Drishti ↔ SMDost)

See [[integrations]] memory for the full architecture, VPS paths, and priority build order.

**Server-to-server auth pattern (X-Service-Key):**
Both Drishti and SMDost use browser-session auth, so we added a shared-secret header
as an alternative auth path. CRM sends `X-Service-Key: <secret>` in the request
header; each app validates it against an env var before falling back to normal session
auth. Config in CRM: `config('services.drishti.service_key')` / `config('services.smdost.service_key')`.

**Pattern for outbound HTTP calls to external apps:**
- Always use a **queued job** (database driver — no Redis on Hostinger).
- Wrap **each** external call in its own try/catch — failure of one must never block the other or break core CRM workflow.
- **Idempotency guard:** check if the external ID is already set before calling; skip if so.
- Log failures with `Log::warning()` (not info — `LOG_LEVEL=warning` on Hostinger drops info).
- Write an `Activity` record on the subject model after successful provisioning (`user_id = null` for system events).
- Use `updateQuietly()` to save external IDs without firing model events (avoids activity-log noise).

**Config/env convention for new integrations:**
```php
// config/services.php
'appname' => [
    'base_url'    => env('APPNAME_API_URL', 'https://app.example.com'),
    'service_key' => env('APPNAME_SERVICE_KEY'),
],
```
Document both vars in `.env.example` with a comment explaining what they match on the other app's side.

**VPS paths (for deploying changes to the other apps):**
- nedsdrishti.in: `/opt/app/agencyos` — Docker Compose, env file `.env.production`, restart with `docker compose up -d app`
- socialmediadost.com: `/root/social-media-dost` — Docker Compose, env file `.env`, restart with `docker compose up -d`
- `docker compose restart app` does NOT re-read env_file — always use `up -d` to pick up new env vars.
