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
  `notes()` morphMany).

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

- **CI/CD:** `.github/workflows/deploy.yml` auto-deploys on **push to `master`**
  (build assets → commit `public/build` → SSH `git reset --hard origin/master` +
  `migrate --force` + caches). **Merging a PR ships it.** Verify live after a
  deploy with `curl` (e.g. `/` → 302 login; a page → 200).
- **Per-change loop (minus the milestone plan):** branch off `master` → build →
  `php artisan test` + `pint` → PR → merge (auto-deploys). Smoke-test on live
  MySQL the same way, wrapping anything that writes in a rolled-back
  `DB::transaction`.
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
- **Help & user guides:** single source = `docs/user-guides/*.md` → in-app
  `/help` (HelpController via league/commonmark) AND PDF handouts
  (`npm run handouts`, headless Chrome — no Playwright dep). Edit the .md once.
- **Staff accounts:** public registration is disabled; admins add users via the
  **Users** screen. Service lines via **Services** (was the Categories stub).
