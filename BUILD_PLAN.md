# NEDS CRM — Build Plan for Claude Code
# Work through milestones in order. One milestone = one Claude Code session/branch.
# After each milestone: run tests, review the diff, commit, deploy to staging,
# let the team try it.

## Milestone 0 — Project scaffold (Day 1)
Prompt to Claude Code:
"Scaffold a Laravel 11 project per CLAUDE.md: Breeze (Blade) auth, Livewire 3,
Tailwind, Pest. Add a `role` enum column to users (admin, manager, sales,
support, accounts). Seed an admin user. Build the dynamic sidebar per the
Menu Controller section of CLAUDE.md: menu_items table + role/user pivots,
seeded with: Dashboard, Attendance, Lead Generation, Sales Department,
Account, Project Updates, Categories, Quotations, Customer, Invoices,
Calling, Emptask, Menu Controller (stub pages are fine). Sidebar renders
from the table based on the logged-in user's permissions. Add the activities
table and a LogsActivity trait."

Acceptance: login works, sidebar is data-driven and role-aware, tests pass
including one proving a hidden menu's route is still blocked by middleware.

## Milestone 1 — Customers & Contacts (Days 2–3)
- Customer CRUD: company name, GSTIN (validated), billing address with state,
  phone, email, website, tags, owner (user), status.
- Contact CRUD nested under customer: name, designation, phone, email, is_primary.
- Customer detail page: tabbed timeline (notes, deals, invoices, tickets —
  tabs can be stubs for now), notes with @mentions stored as plain text.
- Import: CSV upload mapping columns → customers (this is how existing Excel
  data comes in). Show row-level errors, skip duplicates by email/GSTIN.
- Policies: sales sees own + unassigned; manager/admin see all.

## Milestone 2 — Leads & Pipeline (Days 4–6)
- Lead CRUD: name, company, phone, email, source (website/whatsapp/referral/
  cold-call/other), service interested in, estimated value (paise), owner, notes.
- Kanban board (Livewire drag-drop) for deal stages; list view with filters.
- "Convert lead" action → creates Customer (+Contact) and a Deal in one
  transaction, links history.
- Follow-up reminders: next_follow_up_at on leads/deals; daily 9am scheduler
  emails each user their due follow-ups; dashboard widget "Overdue follow-ups".
- Lead capture API endpoint (token-protected) so the company website form can
  POST leads directly.

## Milestone 3 — Quotations & Invoices (Days 7–10)
- Quotation builder: customer, line items (description, SAC, qty, rate, GST%),
  auto CGST/SGST vs IGST per CLAUDE.md, discount, terms, validity date.
- PDF via dompdf with NEDS branding placeholder, amount in words (Indian
  numbering: lakh/crore).
- Quotation states: draft → sent → accepted/rejected. "Convert to invoice".
- Invoice numbering NEDS/{FY}/{seq} with a DB-locked sequence (no gaps, no
  duplicates under concurrency).
- Payments: record against invoice (date, mode: UPI/NEFT/cheque/cash/gateway,
  reference, amount in paise). Partial payments → status partially_paid.
- Payment reminder schedule: 3 days before due, on due date, every 7 days after.
- Recurring invoices: mark an invoice template as recurring (monthly/quarterly,
  start date, optional end date, linked service). Scheduler generates the next
  invoice automatically on the 1st and emails it. Essential for SEO/social/ads
  retainer clients.
- Milestone billing: a quotation can define payment milestones (e.g. 40%
  advance / 40% on UAT / 20% on go-live). Each milestone converts to its own
  invoice when due, all linked to the same deal, with a deal-level view of
  billed vs collected vs remaining. Needed for software dev and AI
  automation projects.
- Reports stub: outstanding receivables by customer.

## Milestone 4 — Projects, Tasks, Tickets (Days 11–14)
- Won deal → "Create project" (name, start/end, assignees).
- Tasks: title, description, assignee, due date, priority, status
  (todo/in_progress/review/done), comments, attachments. "My tasks" view.
- Tickets: customer, subject, description, priority (SLA: urgent 4h, high 8h,
  normal 24h, low 72h business hours), status (open/in_progress/waiting/
  resolved/closed), assignee, thread of replies.
- Email notification to customer contact on ticket create/reply/resolve.
- SLA breach highlighting + manager escalation email.

## Milestone 4b — Attendance, Call Logs, Daily Reports (Days 14–16)
Built per the dashboard mockup (see docs/dashboard-reference.png if added):
- Attendance: dashboard check-in/check-out button, monthly attendance view
  per user, admin correction screen, leave marking. Monthly summary export.
- Call Log: quick "Log a call" action from client/lead pages and global
  navbar; list view filterable by user/date/outcome; calls appear in the
  client timeline.
- Daily Reports: end-of-day form per employee (auto-filled metrics: tasks
  completed today, calls made, leads touched; plus free-text summary).
  Manager view: team's daily reports for any date. Reminder email at 6pm
  if not submitted.
- Service taxonomy applied across deals/projects/quotation lines (seeded:
  SEO, GMB, Website Development, Social Media, Google Ads, Software
  Development, AI Automation).

## Milestone 5 — Customer Portal (Days 17–19)
- Separate guard `portal` with its own login (contacts get portal access
  toggled per contact, invitation email sets password).
- Portal pages (read-mostly): my invoices (view/download PDF, payment status),
  my projects (status only), my tickets (create + reply), company profile.
- Strictly scope every query to the logged-in contact's customer. Write Pest
  tests proving a contact cannot access another customer's data.

## Milestone 6 — AI layer (Days 18–20)
Per CLAUDE.md AI section, behind AI_ENABLED flag:
- AnthropicClient service class (HTTP client, retry once, 30s timeout).
- Lead scoring job + score badge on lead cards.
- "Draft reply" on tickets and leads (modal: editable draft, Send/Discard).
- "Summarize timeline" on customer page.
- AI usage log table: feature, tokens used, created_at (for cost monitoring).

## Milestone 7 — Reports, polish, hardening (Days 23–27)
- Admin dashboard must match the approved mockup layout:
  Row 1 stat cards: Total Clients, Active Clients, Inactive Clients, Tasks
  Overview — each with % change vs last month.
  Row 2: Services Overview donut (projects by service: SEO, GMB, Website
  Development, Social Media, Google Ads, with counts and %) + Task Summary
  (Assigned / Pending / Overdue / Completed counts + stacked distribution bar).
  Row 3: Daily Reports panel (Employee Reports, Service Reports links),
  Project Dashboard panel (Client-wise / Employee-wise / Service-wise
  groupings), Reports panel (Revenue Report, Project Status Report,
  Employee Performance Report).
- Role dashboards: sales (pipeline value by stage, my follow-ups, monthly
  won), accounts (outstanding, collected this month, overdue invoices),
  support (open tickets by priority, SLA at-risk).
- Employee Performance Report: per user per period — tasks completed,
  on-time %, calls made, leads converted, attendance %, daily reports
  submitted. Exportable.
- Revenue Report: by month, by service, by client; recurring vs one-time.
- Menu Controller admin screen: grid of roles × menu items with toggles for
  role defaults, plus a per-user override panel (grant/revoke specific items
  for an individual). Changes apply on next page load (clear menu cache).
  Admin-only. Show a warning in the UI that hiding a menu does not remove
  the underlying permission — that is governed by roles/Policies.
- Global search (clients, leads, deals, invoices, tickets, projects).
- Security pass: rate limit login (5/min), force HTTPS, secure cookies,
  session timeout 8h, 2FA (TOTP) for admin/manager, audit log viewer for admin.
- Backup command: nightly mysqldump to storage + email confirmation; weekly
  copy retained 8 weeks. Document restore procedure.
- Seed realistic demo data; run full Pest suite; fix N+1 queries (install
  Laravel Debugbar locally only).

## Deployment runbook (Hostinger Business)
1. In hPanel create MySQL DB + user; note credentials.
2. Enable SSH if available on the plan; otherwise use hPanel Git deploy or FTP.
3. Upload app one level ABOVE public_html; copy contents of /public into
   public_html; edit public_html/index.php paths to ../app-folder.
4. Create .env on server (APP_ENV=production, APP_DEBUG=false, DB creds,
   ANTHROPIC_API_KEY, MAIL settings — use Hostinger SMTP or a transactional
   provider).
5. composer install --no-dev (via SSH) or upload vendor/ built locally with
   matching PHP version.
6. php artisan migrate --force && php artisan storage:link && config/route/view cache.
7. hPanel → Cron Jobs: `php /home/USER/app-folder/artisan schedule:run`
   every minute, and `php artisan queue:work --stop-when-empty` every minute.
8. Verify SSL is active; set APP_URL to https domain.
9. Create real users, import customer CSV, switch the team over.

## Working-with-Claude-Code tips for the team
- One milestone per session. Start each session with: "Read CLAUDE.md and
  the relevant milestone in BUILD_PLAN.md, then propose your plan before
  writing code." Approve the plan, then let it build.
- Use plan mode for anything touching money, GST, or permissions.
- After Claude Code says it's done: `php artisan test` yourself, click through
  the feature, read the migration files before approving.
- Keep commits small; if a session goes sideways, `git checkout .` and restart
  with a tighter prompt rather than patching confusion.
- Add every decision you make ("we chose X because Y") back into CLAUDE.md —
  it's the project's memory.
