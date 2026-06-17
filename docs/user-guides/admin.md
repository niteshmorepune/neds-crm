# Admin guide

Admins can do everything a manager can, plus manage **users**, **services**, the
**sidebar (Menu Controller)**, the **audit log**, and **backups**.

> Admins must use **Two-Factor Authentication** — see
> [Getting Started](getting-started.md).

## 1. Users — add and manage staff
Public sign-up is disabled, so **you create every staff account**.

- **Users → Add user** → name, email, **role** (Admin / Manager / Sales /
  Support / Accounts), and a temporary password. Leave **Active** ticked.
- Give the person their email + temporary password; they change it on first
  login. Admins/managers will be guided through 2FA setup.
- **Edit** a user to change their name, email, role, or reset their password.
- **When someone leaves:** edit them and **untick Active** instead of deleting —
  this blocks their login but keeps their leads, deals and history intact.
- You **can't** disable, demote, or delete **your own** account (so you can't
  lock yourself out).

## 2. Services — the service-line taxonomy
**Services** lists your offerings (SEO, GMB, Website Development, Social Media,
Google Ads, Software Development, AI Automation…). These power every report's
service breakdown.
- **Add** a new service line, **rename**, set the **sort order**, or **toggle
  active**.
- A service **in use** by leads/deals/projects/tickets can't be deleted — just
  deactivate it so it stays on old records but isn't offered for new ones.

## 3. Menu Controller — who sees what
The **Menu Controller** has two parts:
- **Role grid** — which roles can reach each module. *This controls real access.*
- **Per-user overrides** — show/hide individual sidebar items for one person.
  *Cosmetic only* — it tidies someone's sidebar but does **not** grant or remove
  permission (that's always governed by roles). A banner on the page reminds you
  of this.

Changes apply on the user's next page load.

## 4. Clients — bulk import & deletion
**Import (Clients → Import):** upload a CSV. The template (downloadable from the
import page) has 13 columns including `address_line2`, `owner` (user's full name),
and `tags` (comma-separated). Leave `owner` blank to assign the client to the
importing user. Duplicate emails and GSTINs (including soft-deleted records) are
skipped automatically.

**Deleting a client:** removes the client **and all related records** — deals,
quotations, invoices, projects, tasks, tickets, contacts, notes, and call logs.
This cannot be undone. Use this only when the company record should be wiped
entirely; consider making a client **Inactive** instead if you may need the
history later.

## 5. Audit Log
**Audit Log** (admin) shows who created, updated or deleted records, and when.
Filter by record type or event. Use it to investigate "who changed this?".

## 6. Backups
The database is **backed up automatically every night at 2 AM** (kept 14 daily +
8 weekly copies on the server). You don't need to do anything. To restore from a
backup, follow `docs/backup-restore.md`.

## 7. AI features (optional)
Lead scoring, "Draft with AI" and "Summarize" are **off by default**. To turn
them on, an administrator sets `AI_ENABLED=true` and an Anthropic API key in the
server `.env` (see the deployment docs). If they're off, the buttons simply don't
appear and nothing else changes.

## Tip
Adding a new module/menu item or changing a label is a code change that deploys
automatically — but the menu lives in the database, so after such a change the
menu must be re-seeded on the server (`php artisan db:seed
--class=MenuItemsSeeder --force`).
