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

**Additional roles (someone doing two jobs):** every user has one **primary
role** (the dropdown above) plus an optional set of **additional roles** —
checkboxes further down the same Add/Edit form. Additional roles:
- **Do** expand what the person can directly do (Policies), who they show up
  for in role-targeted notifications (Deal Won, SLA breach, leave-request
  approvals, recurring-invoice due warnings, etc.), owner-picker dropdowns
  (Client/Lead "assign to"), **and their sidebar** — an additional role's
  menu items now appear and are reachable automatically, no separate Menu
  Controller step needed.
- **Do not** change their dashboard panel — that always follows the primary
  role only (e.g. a Support user given Sales as an additional role still
  sees the Support dashboard, but gets the Sales sidebar items too).
- **Do not** affect auto-assignment/routing (new-lead auto-owner, automatic
  task routing to a project's Support assignee) — those also stay
  primary-role-only, by design.
- Menu Controller → per-user overrides still exists for one-off exceptions
  (granting/hiding a single item for one person without giving them a whole
  extra role, or hiding an item their role would normally show).

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
machine's Cloud Server settings should point to `crm.niranjanenterprises.co.in` on
port 443 with HTTPS on.

**Occasional missing punches:** the biometric machine's own stored log can
get trimmed during the day by other software that also reads it (the
"hitech" billing software), so a punch can occasionally never reach the
CRM at all. If someone's check-in or check-out looks wrong or missing on
the Attendance page, try this first:

- Click **Sync from biometric** at the top of the Attendance page
  (admin/manager only). This pulls fresh punches from the machine within
  about a minute — a status line appears showing whether it found anything
  and synced ("Biometric sync completed ...") or hit a problem ("Biometric
  sync failed ..."). If it doesn't resolve it, fall back to the Hitech
  import below.

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
**Services** lists your offerings (SEO, GMB, Website Design & Development,
Social Media, Performance Marketing, Software Development, AI Automation,
AMC Service). These power every report's service breakdown.
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
- If the client's phone number matches an existing client record, a **ticket**
  is created and linked to them. If no match is found, a **lead** is created
  instead (source WhatsApp) — check **Lead Generation**, not Tickets, for
  enquiries from numbers that aren't clients yet.
- Each conversation creates **one ticket** (or one lead) — subsequent
  messages in the same conversation don't create duplicates; for a lead,
  later messages are added as notes on it instead.
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
the project lead (or the project manager if no lead is set yet). This only
fires once per project, not on a schedule.

**✨ Suggest onboarding tasks (opt-in only)** — a button on a project's page
(next to its Tasks list, for whoever can manage that project) that has Claude
suggest EXTRA onboarding tasks beyond the standard checklist above, based
only on the originating deal's notes and quotation line items — e.g. a note
mentioning "client wants a Hindi translation" surfaces a task for that
specifically. It never repeats a task already on the project, and if nothing
in the notes/line items calls for anything extra it says so rather than
padding the list. Nothing is ever created automatically: every suggestion
shows as a ticked checkbox you can untick, and a Task is only created once
you click **Add selected tasks** — same "never floods the task list"
discipline as everywhere else AI touches tasks in this CRM.

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

## 8. Lead capture channels
Leads flow into the CRM automatically from three channels — no manual entry
needed for any of them:

- **Website** — the **niranjanenterprises.com** contact form creates a lead
  on every submission. The message the visitor typed appears in the lead's
  **Notes** tab; service and company fields are captured when filled in. If
  the form was built with hidden UTM fields, the lead's **Campaign** line
  shows which ad/link it came from (see the Lead Source Performance report
  in the manager guide). Configured via `LEAD_CAPTURE_TOKEN` in `.env` — if
  the form stops creating leads, check it matches the Elementor webhook URL.
- **WhatsApp** — a message from a number that isn't an existing client's
  creates a lead (source WhatsApp) instead of a ticket — see Section 5.
- **Meta Lead Ads** (Facebook/Instagram) — a lead form submission on a Meta
  ad creates a lead (source Meta Ads) via a webhook. **Live and configured**
  — see below for how to design a new ad's Instant Form so its leads score
  and report well, and for the webhook setup steps below if this ever
  needs re-registering (new app, new Page, token rotated).

**All new leads auto-assign** to whichever active Sales user currently has
the fewest open leads, so nothing sits unowned waiting for someone to notice
it (this runs regardless of whether AI is enabled — see the AI features
section for the AI-specific parts: scoring, hot-lead alerts, nurture
follow-ups).

**Designing a Meta Instant Form so its leads work well in the CRM:** the
CRM only auto-scores a lead as well as a manually-entered one if the form's
questions give it the same information a rep would ask for. Use Meta's
standard prefill fields for **Full Name, Phone Number, Email, Company
Name** (high completion rate, autofilled from the person's Facebook
profile), then add two custom questions worded specifically so the CRM can
map them automatically:
- **Service** — a multiple-choice question whose options are the **exact
  active Service names** (case-insensitive match, but must be the same
  words) — e.g. "SEO", "GMB", "Website Design & Development", "Performance
  Marketing". A paraphrased option like "Website help" won't match and the
  lead loses its service tag (still lands as a lead, just unscored on
  service fit).
- **Budget** — the question's own wording must contain the word **"budget"**
  (e.g. "What's your approximate monthly budget?"). Multiple-choice ranges
  work fine — "₹10,000–25,000" is parsed and averaged.
  Anything that doesn't match either pattern is preserved as a note on the
  lead rather than dropped, so it's never lost — it just won't feed the
  score automatically.
- Use the **"Higher intent"** form type (adds a review screen before
  submit) over "More volume" — meaningfully fewer fat-finger/junk
  submissions for a small increase in cost per lead.
- The ad's own name in Ads Manager becomes the lead's **Campaign** value in
  the CRM (e.g. "SEO - Pune - July V2") — name your ads something you'd
  recognise in the Lead Source Performance report, not the Ads Manager
  default.

**Setting up Meta Lead Ads** (only needed if re-registering — a new
Facebook Developer App, a new Page, or a rotated token):
1. In the Meta App Dashboard, add the **Webhooks** product, subscribe to the
   **Page** object's `leadgen` field.
2. Callback URL: `https://crm.niranjanenterprises.co.in/api/webhooks/meta-leads`.
   Verify token: any value you choose — set the same value in both Meta's
   dashboard and the server's `META_WEBHOOK_VERIFY_TOKEN`.
3. Set `META_APP_SECRET` (from the app's Basic Settings) and
   `META_PAGE_ACCESS_TOKEN` (a Page access token with `leads_retrieval`
   permission) in the server `.env`, then `php artisan config:cache`.
4. Submit a test lead on the ad form and confirm it appears in **Lead
   Generation** with source **Meta Ads**.

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

How much business each partner has actually brought in — referred clients,
plus won/pipeline/lost deal value attributed via a deal's **Referred by**
field — shows up in **Business Overview** (Reports panel; see [Manager guide
→ Reports](manager.md)). As Admin you see the full financial detail there
(itemized overdue invoices, named client breakdown, itemized upcoming
renewals) — Manager sees the same report with those three itemized sections
trimmed to summary numbers only. Click a partner's name there (or on the
Partners list) to open their page for actual invoiced amounts — a
month-by-month and per-client "Billed — last 6 months" breakdown, plus which
of their clients are unpaid/overdue and for how long (see [Manager guide →
Partner client health](manager.md) for the full breakdown).

## 10. Audit Log
**Audit Log** (admin) shows who created, updated or deleted records, and when.
Filter by record type or event. Use it to investigate "who changed this?".

## 11. Backups
The database is **backed up automatically every night at 2 AM** (kept 14 daily +
8 weekly copies on the server). You don't need to do anything. To restore from a
backup, follow `docs/backup-restore.md`.

## 12. AI features (optional)
Nine AI helpers are built into the CRM, powered by Anthropic's Claude. They are
**off by default** and never take action, send, publish, or score an employee
automatically — they only draft or summarize for a human to review.

**Lead scoring** — when a lead is created or edited, the CRM automatically sends
its details to Claude and stores a **0–100 score** with a one-line reason
(e.g. "Specific service requested, phone and company provided — high intent"),
plus an estimated **budget band**, **urgency**, and **service fit** note. The
score badge appears on the leads list so sales staff can prioritise without
reading every entry. Existing leads (created before AI was enabled) get scored
the next time they are edited and saved. A lead scoring **70 or above** is
flagged 🔥 Hot and its owner gets an immediate bell notification instead of
waiting for the next morning digest — configurable via `AI_HOT_LEAD_THRESHOLD`
in `.env` (default 70).

**Lead auto-assignment** — a new lead with no owner (e.g. from the website
form) is automatically assigned to whichever active Sales user currently owns
the fewest open leads, so leads never sit unowned. This runs independently of
`AI_ENABLED` — it's routing, not an AI feature.

**Draft follow-up / Draft reply (✨)** — a button on leads and tickets. When
clicked, Claude reads the lead/ticket details and history, then writes a suggested
message. The staff member edits it and sends it themselves. Claude never sends
anything automatically.

**Automated lead nurture follow-ups** — daily at 10:30 IST, any New lead its
owner hasn't personally added a note or logged a call on gets an AI-drafted
follow-up at day 1 (first outreach), day 3 (gentle nudge), and day 7 (final,
low-pressure check-in) since it came in. Each draft lands as a staff-only
note on the lead plus a bell notification to the owner — never sent
automatically. The system-generated note created from the original enquiry
(website form / WhatsApp message) doesn't count as a staff touch, so a lead
that's never actually been worked still qualifies. Skips Sundays, same as
the stagnation alerts.

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

**AI weekly owner digest** — every Monday at 9 AM, Admin/Manager get a short
AI-written paragraph synthesizing the week ahead: open pipeline, MRR,
cash expected this month and over the next 3 months, receivables
outstanding (including the 90+ days overdue figure), and how many clients
Client Radar has flagged (and why). It's a synthesis of the existing
Business Overview, Cash Forecast, and Client Radar reports — the email
links to all three — and also appears as a dashboard banner for the rest
of that Monday. Unlike the daily digest, this one is skipped entirely
(no email sent) if AI is turned off, since there's nothing to show beyond
what those three reports already show on their own.

**Team performance summary (✨ Generate AI Summary)** — a button on the
**Employee Performance Report** (Reports panel) that turns the existing
tasks/calls/attendance numbers into a narrative of trends and standouts. For
a Sales rep, once there's enough pipeline history it also names specific
stages where that rep's deals move slower than the team average (e.g.
"averages 18 days in Negotiation before moving a deal on, against the
team's 9 days") — a concrete coaching point instead of a vague "needs
support" line. This only appears once a rep has at least 3 completed stage
transitions to measure, so it may not show for a while on a newer pipeline.
**Visible to Admin/Manager only** — it is never shown to the employee it's
about, so it's a starting point for a conversation, not a rating you share.

**Client Radar suggestions (✨ Suggest action)** — on the **Client Radar** page
(Section 2b), a button per flagged client that has Claude suggest one concrete
next action based on that client's specific signals. Generated on demand per
client, not in a batch — so it costs nothing unless someone clicks it.

**CSAT recovery drafts (✨ Draft recovery message)** — a second button that
appears only on a **Low Satisfaction** flag, grounded in the actual ticket
that was rated poorly (subject, description, rating, and the client's own
comment if they left one) rather than just the flag's summary text.

**Ticket triage suggestion (✨ Suggest priority & assignee)** — on the New
Ticket form, suggests a priority and, if it can match the description to one
of the client's active services, the project lead for that service as a
likely assignee. The service match is always an exact name from that
client's real active services — never a hallucinated one — so if nothing
fits it says so instead of guessing.

**Portal assistant ("Ask about your account")** — the one AI feature clients
trigger themselves rather than staff, on their portal Dashboard. It only ever
sees that client's own invoices, ticket statuses, and project statuses — never
internal notes or another client's data — and is capped at
`AI_PORTAL_ASSISTANT_DAILY_LIMIT` questions per contact per day (default 15,
`.env`) so it can't be run up. See the **AI Usage Report** (Reports panel) to
track how much any of this is actually costing.

**Ask the CRM** (Reports panel, Admin/Manager only) — a free-text business
question box covering pipeline KPIs, Client Radar, revenue, service
breakdown, lead sources, cash forecast, MRR, AR aging, the rep leaderboard,
needs-attention deals, and AI usage. Answering a question is always two AI
calls, not one: the first only picks which of those report types the
question is about — it never touches real data. The second narrates an
answer using the exact real figures that report already computes, shown
right there in the answer's table alongside a link to the full report, so
nothing shown can drift from what the report itself says. A question
outside that fixed list gets a list of what it can currently answer, not
a guess.

**Rating a draft (Helpful / Not helpful)** — after Claude drafts or answers
something (a reply, a summary, a suggestion, a Client Radar or Ask the CRM
answer), a small "Was this useful?" prompt appears once you've had a look.
It's entirely optional — nothing requires it — but the AI Usage Report
below rolls it up per feature, so over time it's a real read on which
features are actually worth the AI spend versus which ones nobody trusts.

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

**Project daily update drafts** — every evening at 6:30 PM (skipped on
Sundays), for each active project with at least one task completed that day,
Claude drafts a short client-facing progress update from the completed task
titles and stores it as a pending note (`ai_generated = true`,
`visible_to_client = false`) — not yet visible anywhere the client can see it.
The project owner gets a bell notification and, on the project's page, a
**Pending Client Update** panel to edit and either **Approve & Send** (flips
the note to `visible_to_client = true`, which is what makes it appear in the
client portal feed, and emails the client's billing contact) or **Discard**.
Admin/Manager can also approve or discard on any project, not just their own.
This differs from the monthly wins note above in one important way: that one
is staff-only forever, meant to be copied and sent manually; this one has a
real send step built in, and is what actually reaches the client once
approved. Projects with no activity that day are skipped silently — no
hollow draft, no AI call spent, no notification.

**Project Updates Digest (leadership oversight)** — every morning at 9:15 AM
(skipped on Sundays), every active Admin/Manager gets one email covering the
whole project daily-update workflow across the team, not just their own
projects:
- **Yesterday's client updates** — how many were drafted, how many got
  approved & sent, how many are still awaiting review.
- **Client updates awaiting review 2+ days** — a table of drafts nobody has
  approved or discarded yet, with the project, its Project Manager, and how
  long it's been waiting. Keeps surfacing every day until someone acts on it.
- **Projects gone quiet 5+ days** — active projects with no completed task
  and no note (of any kind) in the last 5 days, so a project that's stalled
  doesn't go unnoticed just because nobody happened to look at it. New
  projects get a grace period before they can be flagged this way.
Nothing is sent if there's genuinely nothing to report that day — no filler
email. Both thresholds (`--stale-days`, default 2; `--quiet-days`, default 5)
are command options if they ever need adjusting.

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
