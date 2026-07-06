# Admin guide

Admins can do everything a manager can, plus manage **users**, **services**, the
**sidebar (Menu Controller)**, the **audit log**, and **backups**.

> Admins must use **Two-Factor Authentication** — see
> [Getting Started](getting-started.md).

## 1. Users — add and manage staff
Public sign-up is disabled, so **you create every staff account**.

- **Users → Add user** → name, email, **role**, and a temporary password.
  Leave **Active** ticked.
- Give the person their email + temporary password; they change it on first
  login. Admins/managers will be guided through 2FA setup.
- **Edit** a user to change their name, email, role, or reset their password.
- **When someone leaves:** edit them and **untick Active** instead of deleting —
  this blocks their login but keeps their leads, deals and history intact.
- You **can't** disable, demote, or delete **your own** account (so you can't
  lock yourself out).

**Roles available:**

| Role | What they can access |
|---|---|
| Admin | Everything, including users, menu controller, audit log, backups |
| Manager | All modules except user/menu/audit/backup admin |
| Sales | Leads, deals, their own clients, quotations, projects, tasks, tickets, calls |
| Support | Tickets, projects (assigned), clients (read-only), calls, tasks |
| Accounts | Invoices, payments, clients, recurring invoices |
| **Intern** | Clients (read-only), Projects (assigned), Tasks (assigned), Attendance, Daily Reports |

**Biometric Device User ID:** each user record has an optional
**Biometric Device User ID** field. Set this to the numeric ID from the
eSSL attendance machine's Device Users list. Once set, punches from that
person on the biometric machine automatically update their CRM attendance
record (check-in and check-out times). See Section 1a below.

## 1a. Biometric attendance sync (eSSL machine)
The CRM is connected to the **eSSL x 2008** biometric attendance machine
(serial NFZ8243301103). When staff punch in or out on the machine, their
attendance is automatically synced to the CRM — no manual check-in needed.

**How it works:**
- The machine sends each punch to the CRM automatically via the internet.
- The **first punch of the day** sets the check-in time.
- The **last punch of the day** sets the check-out time.
- If a staff member also pressed the manual check-in button on the CRM
  dashboard, the biometric punch will update the record with the exact time
  from the machine.

**To map a staff member to the machine:**
1. On the machine, go to **Menu → User Mgt** to find the person's numeric
   User ID (e.g. 1, 3, 13, 16 …).
2. In the CRM, go to **Users → Edit** the staff member.
3. Enter that number in the **Biometric Device User ID** field and save.

From that point on, the machine's punches update their attendance automatically.

**If the sync stops working**, check that `BIOMETRIC_DEVICE_SERIAL` is set
correctly in the server `.env` and run `php artisan config:cache`. The
machine's Cloud Server settings should point to `crm.talktonitesh.com` on
port 443 with HTTPS on.

**Occasional missing punches:** the biometric machine's own stored log can
get trimmed during the day by other software that also reads it (the
"hitech" billing software), so a punch can occasionally never reach the
CRM at all. If someone's check-in or check-out looks wrong or missing on
the Attendance page:
1. In **hitech**, open that staff member's **Attendance** tab, pick the
   date range, and click **Export To Excel**.
2. In the CRM, go to **Attendance → Import from Hitech**, pick the staff
   member, and upload that file.
3. Review the preview (it shows Hitech's times next to what the CRM
   currently has) and click **Import**. Only the fields Hitech actually
   reports are written — it never erases a value the CRM already has just
   because a cell is blank (e.g. someone who hasn't clocked out yet when
   the export was taken).

## 2. Services — the service-line taxonomy
**Services** lists your offerings (SEO, GMB, Website Development, Social Media,
Performance Marketing, Software Development, AI Automation, AMC Service).
These power every report's service breakdown.
- **Add** a new service line, **rename**, set the **sort order**, or **toggle
  active**.
- A service **in use** by leads/deals/projects/tickets can't be deleted — just
  deactivate it so it stays on old records but isn't offered for new ones.

## 2a. Festivals — the greeting calendar
**Festivals** drives two things: a "🎉 Diwali is in 5 days!" banner everyone
sees on their dashboard, and AI-drafted client greeting content (see below).

**Fixed-date national holidays are always pre-loaded** (Independence Day,
Gandhi Jayanti, Christmas, New Year's Day, Republic Day, Maharashtra Day).

**Lunar/regional festivals shift every year and are NOT pre-loaded by
default.** As of 2026-07-05, the remaining lunar/regional festivals of 2026
were verified against multiple calendar sources and added: Eid-e-Milad-un-Nabi
(26 Aug — moon-sighting dependent, confirm closer to the date), Raksha Bandhan
(28 Aug — one source disagreed with 9 Aug, double-check a regional panchang),
Janmashtami (4 Sep), Ganesh Chaturthi (14 Sep), Navratri/Ghatasthapana
(11 Oct), Dussehra (20 Oct), and Diwali/Lakshmi Puja (8 Nov). Sources:
[drikpanchang.com](https://www.drikpanchang.com/calendars/indian/indiancalendar.html),
[timeanddate.com](https://www.timeanddate.com/holidays/india/2026).
**These dates do not carry forward to next year** — every January, re-verify
and re-add that year's lunar/regional festivals from an official calendar;
don't assume last year's dates still apply. Add a festival with a name and
date; toggle **Active** to hide one without deleting it (e.g. after the year
it applies to has passed).

**AI-drafted client greetings:** every morning, the CRM checks for festivals
7 days out and — for every active client with a **Social Media** or **GMB**
project — automatically drafts a short greeting caption with Claude and adds
it to that project's Content Collaboration queue (tagged with a 🎉 badge).
Nothing is ever posted automatically — a team member always reviews, edits,
and approves it like any other content piece. Requires `AI_ENABLED` (see
Section 12).

## 2b. Client Radar — at-risk / upsell signals
A dashboard banner ("N clients need attention") and a **Client Radar** sidebar
page (Admin/Manager only) flag active clients worth a proactive check-in:

- **No Contact** — no note, call log, or ticket in the last 14 days.
- **Declining Activity** — touches in the last 30 days are well below the
  30 days before that (only shown when No Contact doesn't already apply).
- **Overdue Invoice** — the client has at least one overdue invoice.
- **Growth Opportunity** — the client only uses one of the agency's service
  lines, even though more are active — a natural upsell conversation starter.

Everything on this page is computed live from existing CRM data — nothing is
stored or sent automatically. Click **✨ Suggest action** next to a flagged
client to have Claude draft a short, specific next step (a check-in call, a
service to pitch, tactfully chasing payment) based only on that client's
flags. This is generated on demand, one client at a time — not run as a
batch job — so there's no AI cost unless someone actually looks at a client.

## 3. Menu Controller — who sees what
The **Menu Controller** has two parts:
- **Role grid** — which roles can reach each module. *This controls real access.*
- **Per-user overrides** — show/hide individual sidebar items for one person.
  *Cosmetic only* — it tidies someone's sidebar but does **not** grant or remove
  permission (that's always governed by roles). A banner on the page reminds you
  of this.

Changes apply on the user's next page load.

## 4. Clients — status, bulk import & deletion

**Client status:** clients have three statuses:
- **Prospect** (yellow) — created when a lead is converted. Not yet a paying client.
- **Active** (green) — promoted automatically when their deal is marked **Won**.
- **Inactive** — set manually when a relationship ends.

The Clients list defaults to showing Active clients. Use the status filter to
view Prospects, Inactive, or all. You can also set the status manually on the
client's edit page.

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

## 5. WhatsApp integration
The CRM is connected to **wadesk.in** (your WhatsApp dashboard). When a client
messages your WhatsApp support number with a **new conversation** (or reopens a
resolved one), a support ticket is automatically created in the CRM.

- The ticket appears with a green **WhatsApp** badge on the Tickets list.
- If the client's phone number matches an existing client record, the ticket is
  linked to them. If no match is found, the ticket is still created (staff can
  link it to a client manually).
- Each conversation creates **one ticket** — subsequent messages in the same
  conversation don't create duplicates.
- Staff reply to the client from **wadesk.in** directly. Replies can also be
  logged as ticket notes in the CRM to keep the record complete.

This integration is configured via `COMPANY_WHATSAPP` and `WHATSAPP_WEBHOOK_TOKEN`
in the server `.env`. Contact your developer if the integration stops creating
tickets.

## 6. Scheduled maintenance tasks
The CRM runs `app:dispatch-scheduled-tasks` at **8 AM IST daily** via the cron
scheduler. It scans every active project, matches it to a set of built-in task
templates by service, and creates tasks due today — assigned to the project
lead with an in-app bell notification.

**No configuration is needed** — templates are built into the command and cover
all NEDS service lines (Website Dev, SEO, GMB, Social Media, Performance
Marketing, Software Development, AI Automation, AMC Service). Each doc section
of Kiran's service-task checklist (Technical SEO, On-Page SEO, GMB
Profile & Engagement, etc.) became one consolidated recurring task with the
full checklist in its description — not one task per line item — so the
list stays manageable even with dozens of active projects.

**Backfill a missed date** (e.g. server was down, or a project was just made active):
```bash
php artisan app:dispatch-scheduled-tasks --date=2026-07-01
```
The command is idempotent — running it twice for the same date will not create
duplicate tasks.

**Verify it ran:** open any active project in Emptask and check that tasks were
created today. Or check the server cron log.

**New project onboarding checklist:** when a project is created (manually, or
automatically from a won deal) and its status is Active, the CRM also
one-time-creates a matching onboarding checklist for its service (e.g. SEO
gets "Technical SEO setup", "On-page SEO setup", "Off-page SEO setup", and
"Initial SEO report", each due a few days to a few weeks out) — assigned to
the project lead (or owner if no lead is set yet). This only fires once per
project, not on a schedule.

**How staff see these:** on the Daily Reports page, **My Tasks** groups tasks
by project and collapses these auto-created tasks under a "🔄 routine
maintenance" line (tasks a person assigned directly stay expanded above it).
The distinction is automatic — any task with no creator recorded is treated
as routine maintenance — so nothing needs to be tagged manually.

**How Admin/Manager see these on Emptask:** the company-wide **Emptask** list
defaults to a **"Assigned tasks"** filter, hiding routine maintenance tasks so
267+ auto-created checks don't bury the handful of tasks someone actually
needs to review. Switch the filter dropdown to **"Routine maintenance"** to
audit just those, or **"All tasks"** to see everything — routine tasks are
marked with a 🔄 icon wherever they appear mixed in.

**Team workload summary:** above the filter bar, Emptask shows a one-row-per-
person table — total tasks, a To Do / In Progress / Review / Done breakdown,
and an overdue count (highlighted in red) — combining assigned and routine
tasks so it reflects everyone's real workload, even though routine tasks are
hidden from the detailed list by default. Click a name to jump straight to
that person's full task list.

## 7. NEDS tool integrations (Drishti & SMDost)

The CRM is connected to **nedsdrishti.in** and **socialmediadost.com**. Seven
automated workflows run between the three tools. The full details are in the
**[Integrations guide](integrations.md)**. In summary:

| When this happens in CRM | This happens automatically |
|---|---|
| Deal marked **Won** | Client + user created in Drishti and SMDost |
| Brief fully approved in SMDost | Draft invoice created in CRM; accounts notified |
| Content piece marked **Send to agency** in SMDost | *NEDS-led* content piece auto-created in CRM project (status: *Sent to partner*), pre-filled with copy text |
| Drishti post approved/published | Activity logged on the client's CRM timeline |
| **1st of the month** (7:30 AM) | Monthly content briefs auto-created in SMDost |
| Client opens portal SSO button | One-click login to Drishti or SMDost |
| WhatsApp ticket opened | Drishti context link auto-appended to ticket |

**Keeping integrations healthy:** all events leave a trace in the client's
Activity feed. If an integration stops working, the most common fix is
verifying the server `.env` keys (`DRISHTI_SERVICE_KEY`, `SMDOST_SERVICE_KEY`,
`PORTAL_SSO_SECRET`) and running `php artisan config:cache`. See the
[Integrations guide](integrations.md) for step-by-step troubleshooting.

## 8. Website lead capture
The **niranjanenterprises.com** contact form automatically creates a lead in the
CRM whenever someone submits it. No manual action is needed.

- New leads land in **Lead Generation** with source **Website** and status **New**,
  unassigned.
- Assign them to a sales person as soon as possible so the enquiry doesn't sit
  cold.
- The message the visitor typed appears in the lead's **Notes** tab.
- Service and company fields are captured when the visitor fills them in.

This integration is configured once on the server (a secret token in `.env`).
You don't need to touch anything — if the contact form stops creating leads,
check that the server's `LEAD_CAPTURE_TOKEN` matches what's configured in the
Elementor webhook URL.

## 9. Partners — content agency directory
**Partners** in the sidebar is a directory of the external content agencies NEDS
collaborates with. Managers and admins can add, edit, and delete partner records.

Each partner needs only a **name**. Email and phone are optional but useful for
quick reference when you need to contact the agency.

Once a partner is registered, staff can assign them to content pieces inside
projects (see [Manager guide → Content collaboration](manager.md)).

**Deleting a partner** is allowed only when no content pieces are linked to
them — the CRM will block the delete and show an error if any pieces still
reference that partner.

## 10. Audit Log
**Audit Log** (admin) shows who created, updated or deleted records, and when.
Filter by record type or event. Use it to investigate "who changed this?".

## 11. Backups
The database is **backed up automatically every night at 2 AM** (kept 14 daily +
8 weekly copies on the server). You don't need to do anything. To restore from a
backup, follow `docs/backup-restore.md`.

## 12. AI features (optional)
Eight AI helpers are built into the CRM, powered by Anthropic's Claude. They are
**off by default** and never take action, send, publish, or score an employee
automatically — they only draft or summarize for a human to review.

**Lead scoring** — when a lead is created or edited, the CRM automatically sends
its details to Claude and stores a **0–100 score** with a one-line reason
(e.g. "Specific service requested, phone and company provided — high intent").
The score badge appears on the leads list so sales staff can prioritise without
reading every entry. Existing leads (created before AI was enabled) get scored
the next time they are edited and saved.

**Draft follow-up / Draft reply (✨)** — a button on leads and tickets. When
clicked, Claude reads the lead/ticket details and history, then writes a suggested
message. The staff member edits it and sends it themselves. Claude never sends
anything automatically.

**Summarize** — a button on client pages and tickets. Claude reads the full
timeline (notes, calls, interactions) and produces a short paragraph summarising
the situation. Useful when picking up a colleague's account.

**Festival greeting drafts** — every morning, for clients with an active Social
Media or GMB project, Claude drafts a festival greeting caption 7 days ahead of
each entry in the **Festivals** calendar (Section 2a) and adds it to that
project's Content Collaboration queue as a draft. A team member always reviews
and approves it before anything is scheduled or published.

**AI daily-priorities digest** — every staff member with anything due gets a
short AI-written "here's your day" line at the top of their 9 AM morning digest
email, which also appears as a dashboard banner for the rest of that day. It's
generated only from that person's own tasks/follow-ups.

**Team performance summary (✨ Generate AI Summary)** — a button on the
**Employee Performance Report** (Reports panel) that turns the existing
tasks/calls/attendance numbers into a narrative of trends and standouts.
**Visible to Admin/Manager only** — it is never shown to the employee it's
about, so it's a starting point for a conversation, not a rating you share.

**Client Radar suggestions (✨ Suggest action)** — on the **Client Radar** page
(Section 2b), a button per flagged client that has Claude suggest one concrete
next action based on that client's specific signals. Generated on demand per
client, not in a batch — so it costs nothing unless someone clicks it.

**Monthly wins note drafts** — on the 1st of each month, for every active client
with an assigned owner who had at least one task completed, ticket resolved,
payment received, or (for clients Drishti manages) a post published, audit
completed, or marketing action item done the month before, Claude drafts a
short "here's what we delivered" note and adds it to that client's Notes tab
(staff-only, marked "AI-drafted monthly update"). The owner gets a bell
notification. Nothing is sent to the client automatically — the account
manager reviews, personalizes, and sends it themselves. Clients with nothing
to report that month are skipped entirely (no hollow note, no AI call spent).
The Drishti numbers are pulled live via a service-to-service call — if
Drishti is unreachable that day, the note still drafts from whatever the CRM
itself knows (tasks/tickets/payments).

**To turn on:** add these two lines to the server `.env`, then run
`php artisan config:cache`:
```
AI_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...
```
If the features are off, the buttons/drafts/digests simply don't appear and
nothing else changes. Usage is billed by Anthropic per request (very low cost
for this volume).

## Tip
Adding a new module/menu item or changing a label is a code change that deploys
automatically — but the menu lives in the database, so after such a change the
menu must be re-seeded on the server (`php artisan db:seed
--class=MenuItemsSeeder --force`).
