# Admin guide

Admins can do everything a manager can, plus manage **users**, **services**, the
**sidebar (Menu Controller)**, the **audit log**, and **backups**.

> Admins must use **Two-Factor Authentication** — see
> [Getting Started](getting-started.md).

## 1. Clients — status, bulk import & deletion

**Client status:** clients have three statuses:
- **Prospect** (yellow) — created when a lead is converted. Not yet a paying client.
- **Active** (green) — promoted automatically when their deal is marked **Won**.
- **Inactive** — set manually when a relationship ends.

The Clients list defaults to showing Active clients. Use the status filter to
view Prospects, Inactive, or all. You can also set the status manually on the
client's edit page. The **Total Clients / Active / Prospect / Inactive**
cards at the top of the list are clickable shortcuts to the same filter —
click a number instead of using the dropdown.

**Export CSV (admin-only):** both the Clients and Lead Generation lists
have an **Export CSV** button — deliberately not shown to any other role,
this is a raw data-extraction ability rather than a day-to-day CRM action.
It exports whatever the list is currently filtered/searched to, so filter
first (e.g. to Prospect, or to a specific owner) if you want a narrower
export.

**Services column:** the Clients list shows each client's currently active
services (a badge per service, e.g. "SEO", "GMB") — this is never manually
entered. It's derived live from that client's active recurring service
templates and non-completed projects, so it always matches what's actually
on their Services tab. Adding, pausing, or completing a service updates this
column automatically. Three or more services show as the first two plus a
"+N more" — hover it to see the full list, or click to expand it inline.

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

## 1a. Resources — shared files and quick-access links
**Resources** in the sidebar has two tabs; add/edit/delete on both is
Admin/Manager only.

**Files** is a shared internal file library — the latest plugin build for
Support, the GST certificate for Accounts, templates, SOPs, anything the
team would otherwise hunt for over chat. Add a **title**, the **file**
itself, and optionally a **Category** (Plugins & Software, Certificates &
Compliance, Templates & Documents, Policies & SOPs, Other). A file's
metadata (title/description/category/visibility) can be edited later, but
the file itself can't be replaced in place — delete it and upload a new
version instead.

**Links** is a company-wide list of links the team reaches for constantly —
most often to *hand to a client or lead* (a hosting signup link when a
client asks to buy hosting, a payment-gateway registration link when a
client wants one set up, a scheduling link to book a call with Sales or
Support), plus a few genuinely internal ones (our own hosting panel login,
training videos on Drive). Add a **label**, a **URL**, and optionally a
**Department** (Sales/Support/Accounts/Admin/Development/Design/General/
Operations/Technical/HR) and a **Purpose**:
- **Client Signup Link** — hand this to a client/lead so *they* register
  or buy something themselves (hosting, a domain, a payment gateway).
- **Scheduling Link** — book a call/meeting (e.g. Connect with Sales
  Team, Connect with Support Team).
- **Team Reference** — internal material the team consults, not shared
  out (training videos, SOPs, internal docs).
- **Internal Tool Access** — NEDS's own admin-panel logins for
  infrastructure the team manages itself (our hosting control panel,
  domain registrar account, Workspace admin console).

Both tabs' lists group by category/department and can be filtered
independently, so a growing list stays easy to scan. Categorising is
optional; an uncategorised item just shows under "Uncategorized" rather
than being guessed at.

**Visible to** (both tabs) restricts who can see a specific file or link —
pick one or more roles (e.g. Accounts, Support) and only staff holding one
of those roles will see it in their list; everyone else won't. Leave it
blank to show the item to everyone, which is the default and matches every
existing link's current behaviour. Admin and Manager always see everything,
restricted or not, so oversight is never affected by this setting.

Every **client's page** also has its own **Links** tab — that client's
website, Google Business Profile, socials, Google Drive folder, Google
Meet link, payment links, or anything else specific to serving them.
Anyone who can open the client's page can see these (subject to the same
role restriction if one is set); editing them follows the same rule as
everything else on that page (Admin/Manager always, Sales for their own/
unassigned clients).

## 2. Client Radar — at-risk / upsell signals
A dashboard banner ("N clients need attention") and a **Client Radar** sidebar
page (Admin/Manager only) flag active clients worth a proactive check-in:

- **No Contact** — no note, call log, or ticket in the last 14 days.
- **Declining Activity** — touches in the last 30 days are well below the
  30 days before that (only shown when No Contact doesn't already apply).
- **Overdue Invoice** — the client has at least one overdue invoice.
- **Growth Opportunity** — the client only uses one of the agency's service
  lines, even though more are active — a natural upsell conversation starter.
- **Low Satisfaction** — at least one ticket rated 2/5 or below by the client
  in the last 60 days (clients rate a ticket once it's Resolved/Closed, from
  the client portal).

Each flagged client also shows a **Health Score** (0–100, list sorted worst
first): starts at 100, loses 30 for No Contact, 20 for Declining Activity,
25 for Overdue Invoice, 25 for Low Satisfaction (Growth Opportunity never
costs points — it's a positive signal). The same score shows as a badge on
the client's own Client 360 page.

Everything on this page is computed live from existing CRM data — nothing is
stored or sent automatically. Click **✨ Suggest action** next to a flagged
client to have Claude draft a short, specific next step (a check-in call, a
service to pitch, tactfully chasing payment) based only on that client's
flags. This is generated on demand, one client at a time — not run as a
batch job — so there's no AI cost unless someone actually looks at a client.
On a **Low Satisfaction** flag, a second button — **✨ Draft recovery
message** — writes a client-facing apology grounded in the actual ticket
that was rated poorly.

## 2a. Best Employee of the Quarter — quarterly AI-suggested recognition
**Best Employee** in the sidebar (everyone can see it — Admin/Manager get a
review queue, everyone else only their own past approved awards). Every
financial-year quarter (Apr–Jun/Jul–Sep/Oct–Dec/Jan–Mar), a scheduled job
reuses the same numbers behind Section 17's Employee Performance ranking
(tasks, on-time %, calls, leads converted, attendance, daily reports) to
suggest one winner per department (Sales/Support/Accounts/Intern/Telecaller)
plus a company-wide "Best Employee of the Quarter," and drafts a short
citation for each with AI. A department needs 2+ eligible people that
quarter to get a suggestion at all — never a fabricated "winner of one."

Nothing is announced automatically. Each suggestion is a **Pending review**
card for Admin/Manager: approve as-is, reassign the **Winner** to a
different eligible person in that department, and/or edit the citation
before confirming. Approving notifies the winner and posts the citation to
the Notice Board in one step; rejecting just discards that quarter's
suggestion. It's recognition only — a downloadable certificate PDF, no
reward amount tracked in the CRM (see Section 5 in `sales.md` for the
separate, money-based Sales Incentive module). **Generate / regenerate** on
the same page re-runs a quarter on demand — it never overwrites an
already-approved award.

## 3. Partners — content agency directory
**Partners** in the sidebar is a directory of the external content agencies NEDS
collaborates with. Managers and admins can add, edit, and delete partner records.

Each partner needs only a **name**. Email and phone are optional but useful for
quick reference when you need to contact the agency.

**Bills as reseller for** (Edit page, optional): set this only when the
partner resells NEDS's services under their own name to their own clients
— e.g. an agency that has its own client base and expects NEDS to invoice
*them*, not each individual client. Pick the Customer record that should
receive the actual GST bill. Once set, every new quotation, invoice, or
recurring invoice created for one of that partner's referred clients
automatically bills the chosen customer instead — the originally-picked
client stays linked for internal tracking, only the GST bill-to party
changes. Leave this blank for a normal referral partner (the common case) — those
clients are billed directly as usual, and the partner can instead be set
up with a **Commission rate** (also on the Edit page) to earn a percentage
of each referred deal's value when it's won.

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
of their clients are unpaid/overdue and for how long. For a **reseller**
partner (one with a "Bill to" customer set), that same page also shows a
"Your Account" section with their own real consolidated invoices, since
their referred clients are billed to that one account rather than
individually (see [Manager guide → Partner client health](manager.md) for
the full breakdown).

## 4. Notice Board — post staff/client announcements
**Notice Board** in the sidebar (Admin/Manager only) is where you post
time-bound notices — office closures, holiday reminders, policy changes,
service updates. Each post has a **title**, a **message**, an **audience**
(Staff only, Clients only, or both), a **start** time, and an optional
**end** time — leave the end date blank for a standing notice with no
expiry. Check **Pin to top** to keep an important notice above newer ones.

A Staff or Both post shows as a dismissible banner at the top of the
**Dashboard** for every internal role (see [Getting Started → Notice
Board](getting-started.md)); a Clients or Both post shows the same way on
the **Client Portal** home page. Only posts within their start/end window
are shown — there's nothing to manually take down once a notice's end date
passes. Dismissing is per-browser (not tracked per person), so a notice can
reappear if someone checks from a different device.

## 5. Team Nudges — targeted reminders + who's actually done them
**Team Nudges** in the sidebar (Admin/Manager only) is different from the
Notice Board: a notice is a broadcast announcement, a nudge is a targeted,
trackable to-do you assign to a role. Each nudge has a **title**, an optional
**description**, a **target** (Everyone, or one specific role), a
**recurrence** (One-time, or Weekly), and — weekly nudges only — an optional
**auto-detect** check that clears the reminder automatically the moment the
targeted person does the real thing (e.g. logs a Ticket), no manual click
needed from them.

Every targeted, active person sees the nudge as a card on their own
Dashboard (see [Getting Started → Reminders](getting-started.md)), with
**Done**/**Snooze 3d** buttons. Below the nudge list, the **team completion
overview** on this page shows the real per-person status for every active
nudge — Pending, Snoozed, or Done, plus how it was completed (manual or
auto) and when. A person snoozing their own view never changes what you see
here — snoozed is shown as its own status, never quietly folded into "Done,"
so this page always reflects reality regardless of who's snoozed what.

## 6. Services — the service-line taxonomy
**Services** lists your offerings (SEO, GMB, Website Design & Development,
Social Media, Performance Marketing, Software Development, AI Automation,
AMC Service). These power every report's service breakdown.
- **Add** a new service line, **rename**, set the **sort order**, or **toggle
  active**.
- A service **in use** by leads/deals/projects/tickets can't be deleted — just
  deactivate it so it stays on old records but isn't offered for new ones.

## 7. Festivals — the greeting calendar
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
Section 17).

## 8. Subscriptions — internal renewal reminders
**Subscriptions** in the sidebar is admin-only (not even managers see this —
it's internal vendor/billing info, unlike most other admin-ish modules).
Track the tools NEDS itself pays for — the Claude subscription, Hostinger
hosting, domain renewals, and anything else — so renewals stop being tribal
knowledge.

Each entry has a **name**, optional **vendor**, **cost per cycle**, a
**billing cycle** (Monthly/Quarterly/Yearly), a **next renewal date**, and
**how many days before** to be reminded. A daily scheduled command
(`app:send-subscription-renewal-reminders`, 08:45 IST) checks every active
subscription and, once it falls inside its reminder window, sends **both** an
email and a bell notification to every active admin — you won't miss it even
on a day you don't log in.

**Once a renewal date passes, it's treated as auto-renewed**: the date rolls
forward one billing cycle on its own (handling several missed cycles at once
if the reminder command didn't run for a while), and the reminder guard
resets so the next cycle gets its own reminder in turn. If you actually
cancel something instead of renewing it, uncheck **Active** on that entry —
otherwise it will keep rolling forward and reminding you indefinitely.

**Tip:** keep the reminder lead time shorter than the billing cycle (e.g. 7
days for a monthly subscription, not 30) — a lead time close to or longer
than the cycle length can cause the reminder to fire on back-to-back days.

## 8a. Expenses — daily office spend
**Expenses** in the sidebar (Admin/Manager/Accounts) tracks day-to-day
office spend — tea/refreshments, travel, stationery, internet, fuel, rent,
utilities, or other. Deliberately no approval workflow, matching how
Subscriptions/Partners already work: **Expenses → New Expense**, pick a
category, amount, description, and date, and save. The list filters by
month and/or category with a running **Total** for whatever's currently
filtered.

## 9. Users — add and manage staff
Public sign-up is disabled, so **you create every staff account**.

- **Users → Add user** → name, email, and **role**. There's no password
  field here — as soon as you save, the CRM automatically emails that
  address a **"Set my password"** link so the new person chooses their own
  password before they ever log in. Leave **Active** ticked. Admins/
  managers will be guided through 2FA setup on their first login.
- If the invitation email doesn't arrive, or the new hire waits too long
  and the link expires (after 60 minutes), they can request a fresh one
  themselves via **Forgot your password?** on the login page — see
  `troubleshooting.md` Section 17a.
- **Edit** a user to change their name, email, or role, or to set a
  password for them directly (e.g. if they're unreachable by email) —
  leave the password field blank to keep their current one.
- The **Edit** page also has an **Internal Notes** card — feedback, areas
  of improvement, follow-up actions — visible only here, never to the
  employee or anyone outside Users access. This is the same notes feature
  as **Employee 360°** in the sidebar (Manager guide, Section "Employee
  360°") — that page reaches the same notes without needing full Users
  access, alongside the employee's performance, workload, tickets, and
  attendance in one place.
- **When someone leaves:** edit them and **untick Active** instead of deleting —
  this blocks their login but keeps their leads, deals and history intact. If
  they're a Sales user still holding open leads, the form won't let you save
  until you pick who takes those leads over (a **Hand over open leads to**
  dropdown appears) — this hands them off through the same Reassign mechanism
  the sales team uses themselves (see the Sales guide), so the new owner gets
  notified and a note is left on each lead explaining why it moved. Leave the
  dropdown on "Leave assigned to [name]" if you'd rather sort it out manually.
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
| Sales | Their own (or unowned) leads and clients, deals, quotations, projects, tasks, tickets, calls |
| Support | Tickets, projects, clients (read-only), calls, tasks — see below, this is also who a project's auto-created tasks route to |
| Accounts | Invoices, payments, clients, recurring invoices |
| Intern | Clients (read-only), Projects (assigned), Tasks (assigned), Attendance, Daily Reports |
| **Telecaller** | Lead Generation (view/update only leads assigned to THEM, via a separate Telecaller field on the lead), Calling. No Deals, Quotations, Invoices, Incentives, Tickets, or Clients |

**Giving a real job title the right CRM role:** the CRM only has the seven
roles above — there's no field for "Graphic Designer." Map the actual job to
whichever role gives the closest real access, then use Menu Controller
(Section 10) to tidy their sidebar if it still shows things they'll never use.

| Real job title | Set as | Why |
|---|---|---|
| Developer, Graphic Designer, UI/UX Designer, Website Designer, Social Media Executive, Digital Marketing Executive, Performance Marketing Executive | **Support** | These are all ongoing project/delivery work — exactly what Support is scoped for. A project's onboarding checklist and its recurring monthly maintenance tasks (Section 14) route to whichever of that project's assignees holds the Support role first, so add them as an **assignee** on the project, not just give them the role |
| Telecaller | **Telecaller** | A dedicated role (added 2026-07-26) — Lead Generation + Calling only. Since 2026-09-03 they see only leads assigned to THEM (a separate "Telecaller" field on the lead, independent of the Sales "Owner" field) and can update status/notes on those, but cannot create, convert, or delete a lead, and have no access at all to Deals/Quotations/Invoices/Incentives/Tickets/Clients. New leads auto-assign to both a Sales owner AND a Telecaller independently (two separate round-robins); you can also assign or reassign a lead's Telecaller by hand from its Edit page (Section 6) |
| Team Lead / Studio Manager | **Manager** | For anyone overseeing other people's work, regardless of department |
| Trainee / Temp, any function | **Intern** | Deliberately narrower than Support (no Tickets, no Calling) — a safer default than Support for someone new or unproven, whatever their eventual title will be |

**Biometric Device User ID:** each user record has an optional
**Biometric Device User ID** field. Set this to the numeric ID from the
eSSL attendance machine's Device Users list. Once set, punches from that
person on the biometric machine automatically update their CRM attendance
record (check-in and check-out times). See Section 9a below.

## 9a. Biometric attendance sync (eSSL machine)
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

## 10. Menu Controller — who sees what
The **Menu Controller** has three parts:
- **Sidebar section** — a dropdown per item picking which collapsible
  section it appears under in everyone's sidebar (My Work, Sales &
  Pipeline, Finance, Delivery & Support, Team & Insights, Team Tools,
  Admin & Config — Team & Insights was split into two groups on
  2026-08-26 so no section has more than ~10 items when expanded).
  *Cosmetic only* — purely how the sidebar is organized, it never affects
  who can reach a module.
- **Role grid** — which roles can reach each module. *This controls real access.*
- **Per-user overrides** — show/hide individual sidebar items for one person.
  *Cosmetic only* — it tidies someone's sidebar but does **not** grant or remove
  permission (that's always governed by roles). A banner on the page reminds you
  of this.

Changes apply on the user's next page load. The sidebar always opens with
exactly one section expanded — whichever one contains the page you're
currently on — and every other section starts collapsed; click any
section's heading to expand it further while you're browsing, but that
doesn't stick once you navigate elsewhere (2026-08-26: replaced the
earlier per-browser-remembered collapse state, which could leave several
sections expanded at once and made the sidebar unpredictably long).

## 11. Audit Log
**Audit Log** (admin) shows who created, updated or deleted records, and when.
Filter by record type or event. Use it to investigate "who changed this?".

## 12. Backups
The database is **backed up automatically every night at 2 AM** (kept 14 daily +
8 weekly copies on the server). You don't need to do anything. To restore from a
backup, follow `docs/backup-restore.md`.

## 13. WhatsApp integration
The CRM is connected to **wadesk.in** (your WhatsApp dashboard), which runs
**two separate numbers**: a **Support** line for existing clients and a
**Marketing** line for pre-sale enquiries. Which line a message came in on
decides how the CRM handles it:

- The CRM always checks the phone number against existing clients **first**,
  regardless of which line the message came in on. If it matches an
  existing client, that client is who it's logged against — a known client
  never becomes a new lead, on either number.
- **Support line, known client** — a **ticket** is created and linked to
  them.
- **Support line, no match** — a **lead** is created instead (source
  WhatsApp).
- **Marketing line, no match** — a **lead** is created (source WhatsApp).
  This is deliberate: the marketing number is for pre-sale traffic, so an
  unknown number texting it shows up where Sales/Telecaller will see it.
- **Marketing line, known client** — never a ticket and never a lead. The
  message is logged as a **note on the client's own record** instead, so
  Sales/Support/whoever's looking still sees it without it being tracked
  as a fresh sales opportunity. (Before 2026-09-03 this used to always
  create a lead even for an existing client — fixed after a real client,
  NSS Secure Solutions, got wrongly added to Lead Generation this way.)
- Each conversation creates **one ticket** (or one lead) — subsequent
  messages in the same conversation don't create duplicates; for a lead,
  later messages are added as notes on it instead.
- Staff reply to the client from **wadesk.in** directly, or — for a
  **lead** — from the note box on the lead's own CRM page, using the "Also
  send as WhatsApp reply" checkbox (see the Sales/Telecaller guides for the
  staff-facing walkthrough). Ticket replies still go out automatically whenever a non-internal
  reply is added, same as before.

**Deal-Won handoff message:** the moment a deal is marked **Won**, the CRM
automatically sends the new client a WhatsApp template message on the
**Support** number, welcoming them and telling them this is now their
support channel going forward — the actual handoff from the marketing
conversation to the support one. This needs a template that's been approved
both in Meta Business Manager and on wadesk.in's own Templates page; until
`WADESK_HANDOFF_TEMPLATE_NAME` is set in the server `.env`, this step is
silently skipped (logged, never blocks the deal being won).

**Visibility Audit payment confirmation:** when someone pays for a Visibility
Audit offer tier (`/offers/visibility-audit`), the CRM sends them a WhatsApp
template confirming the payment, on the **Marketing** number
(`WADESK_MARKETING_NUMBER`) — same no-op-until-approved contract as the
handoff message above, gated by `WADESK_VISIBILITY_AUDIT_TEMPLATE_NAME`.

**Visibility Audit first invite:** Meta's own Lead Ads form never sends a
submitter anywhere — it just captures their name/phone/email. For a Meta
Ads lead tagged the **GMB** service specifically (not every Meta lead —
inviting someone who inquired about SEO or a website to a Google Business
Profile audit offer would be off-target), the CRM automatically sends a
first WhatsApp invite the moment the lead is created, on the **Marketing**
number, gated by `WADESK_VISIBILITY_AUDIT_FIRST_INVITE_TEMPLATE_NAME` —
same no-op-until-approved contract as every other template here. Each lead
is only ever invited once (`leads.visibility_audit_invited_at`).

**Visibility Audit recovery nudges:** a lead who lands on the offer page or
reaches checkout but never pays is automatically nudged over WhatsApp — no
one has to notice and follow up manually. A scheduled job checks every 30
minutes for a lead stuck at checkout 2+ hours or the landing page 4+ hours,
and sends them an approved template with a button back to where they left
off, plus a required "Stop promotions" opt-out (this is Marketing-category
content per Meta's rules, not a plain order update). Gated by
`WADESK_VISIBILITY_AUDIT_RECOVERY_CHECKOUT_TEMPLATE_NAME` /
`WADESK_VISIBILITY_AUDIT_RECOVERY_LANDING_TEMPLATE_NAME` — each stage stays
silently off until its own template is approved and its env var set. A
human can still see the full funnel (eligible → invited → viewed offer
page → reached checkout → paid) and follow up on the same stuck leads any
time via **Lead Generation → VA Recovery**, whether or not the
automated invite/nudge has fired yet.

**Visibility Audit delivery-failure feedback:** wadesk.in's own send call
only confirms it *accepted* a request — WhatsApp's real delivery outcome
(e.g. Meta's "healthy ecosystem engagement" quality throttle, error
131049) arrives later, asynchronously. wadesk.in now forwards that back
to the CRM the moment it happens, which downgrades the matching touch
from "Sent" to "Failed" (with the real reason) on the **VA Funnel
Analytics** message log and failed-sends callout — so a genuinely
undelivered message no longer sits shown as a clean success forever. No
action needed here; this is wired between the two apps automatically
(`CRM_MESSAGE_FAILED_URL` on wadesk.in's side).

**Visibility Audit "audit in progress" retry limit:** the ~30-minute
post-payment "work has started" WhatsApp/email update (see Sales
Section 5, step 2) automatically retries a failing send every 15
minutes, but only up to **5 attempts per channel** — WhatsApp and email
are tracked independently, so one channel giving up never stops the
other from still trying. Once a channel hits 5 failed attempts it stops
retrying that purchase for good and logs a warning for manual
follow-up; the failed sends already logged stay visible on the **VA
Funnel Analytics** message log exactly as before, they just won't
accumulate further automatic retries. If a client says they never got
this update, check the message log for that lead — a run of 5 failures
(commonly an unapproved/misnamed template or a bad phone/email on file)
means it's now waiting on a human, not still quietly retrying.

**Managing which staff see which line:** this is configured on **wadesk.in
itself**, not the CRM — Admin → Agents page has a checkbox per staff member
for each line, and Admin → Numbers is where the lines themselves (and their
Meta credentials) are managed. The CRM has no visibility into or control
over this — if someone should be replying to marketing-line leads but can't
see the conversation in wadesk.in, that's a wadesk.in Agents-page grant, not
a CRM permission.

This integration is configured via `WADESK_API_URL`/`WADESK_SERVICE_KEY`
(outbound replies), `WHATSAPP_WEBHOOK_TOKEN` (inbound), `WADESK_SUPPORT_NUMBER`
(which line is "Support" for the routing logic above), `WADESK_MARKETING_NUMBER`,
`WADESK_HANDOFF_TEMPLATE_NAME` (the Deal-Won message),
`WADESK_VISIBILITY_AUDIT_TEMPLATE_NAME` (the payment confirmation), and
`WADESK_VISIBILITY_AUDIT_RECOVERY_CHECKOUT_TEMPLATE_NAME` /
`WADESK_VISIBILITY_AUDIT_RECOVERY_LANDING_TEMPLATE_NAME` (the recovery
nudges) in the server `.env`. Contact your developer if the integration
stops creating tickets/leads or a template message stops sending.
(`COMPANY_WHATSAPP` is a separate, unrelated setting — just the number
shown on client-facing WhatsApp buttons elsewhere in the app, not part of
this integration.)

## 13a. Telegram lead alerts
Every new lead also posts a short alert (name, phone number, source, assigned
rep, and a link back into the CRM) to one shared Telegram group — a second, always-on
place to notice a new lead land, alongside the in-app notification bell.
To set it up: message **@BotFather** on Telegram, create a bot (`/newbot`),
and copy the token it gives you into `TELEGRAM_BOT_TOKEN`. Add that bot to
your team's Telegram group, send any message in the group, then open
`https://api.telegram.org/bot<token>/getUpdates` in a browser and copy the
negative number under `"chat":{"id":...}` into `TELEGRAM_CHAT_ID`. Until
both are set in the server `.env`, this step is silently skipped (logged,
never blocks lead creation).

## 14. Scheduled maintenance tasks
The CRM runs `app:dispatch-scheduled-tasks` at **8 AM IST daily** via the cron
scheduler. It scans every active project, matches it to a set of built-in task
templates by service, and creates tasks due today — assigned to a Support-role
project assignee, falling back to a pivot-role "lead" team member, then the
project owner. **This never lands on a Sales user at any step** (fixed
2026-07-29) — a Sales rep is frequently a project's owner simply because
they were the deal owner who won it, but these are delivery/maintenance
checks, not sales work. If nobody appropriate is on the project, the task
is skipped for it that day rather than routed to Sales.

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

## 15. NEDS tool integrations (Drishti & SMDost)

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

## 16. Lead capture channels
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
  creates a lead (source WhatsApp) instead of a ticket — see Section 13.
- **Meta Lead Ads** (Facebook/Instagram) — a lead form submission on a Meta
  ad creates a lead (source Meta Ads) via a webhook. **Live and configured**
  — see below for how to design a new ad's Instant Form so its leads score
  and report well, and for the webhook setup steps below if this ever
  needs re-registering (new app, new Page, token rotated).
  Meta also auto-sends a WhatsApp message on the submitter's behalf right
  after they submit the form — the CRM recognises this as the same enquiry
  (matched by phone number) and adds it as a note on the one lead, instead
  of creating a second lead for the same person.

**All new leads auto-assign** to whichever active Sales user currently has
the fewest open leads, so nothing sits unowned waiting for someone to notice
it (this runs regardless of whether AI is enabled — see the AI features
section for the AI-specific parts: scoring, hot-lead alerts, nurture
follow-ups) — unless a **Lead Assignment Rule** below overrides it for that
lead's campaign or service.

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

## 16a. Lead Assignment Rules — routing a campaign or service to a specific rep
By default every new lead auto-assigns to whichever active Sales user
currently has the fewest open leads. **Lead Assignment Rules** (Admin/
Manager, sidebar under Admin & Config) let you override that for a specific
Meta ad campaign or a whole service line — e.g. so a new "CRM & ERP" ad
campaign always routes to a particular rep instead of drifting to whoever's
least loaded that week.

- **Match by campaign name** — an exact match against the lead's **Campaign**
  value (the ad's own name in Meta Ads Manager, or a form's `utm_campaign`).
  Use this to route one specific ad.
- **Match by service** — matches any lead tagged with that service, from any
  source. Use this to route a whole service line rather than one ad — a
  campaign match always wins over a service match if a lead could match both.
- **Match Visibility Audit — Paid** — routes a lead the moment it pays for
  the Visibility Audit offer, but only while that lead is still unowned. It
  never reassigns a lead that already has an owner, even one assigned by
  plain round-robin moments earlier — use Reassign on the lead itself for
  that.
- Only **active Sales users** can be a rule's target. If a rule's target is
  later deactivated or moved off Sales, the rule stops applying and new
  matching leads fall back to the normal least-loaded assignment — it doesn't
  error or assign to someone ineligible.
- Only one **active** rule is allowed per campaign/service/VA-Paid at a time.
  To change what a rule matches, delete it and add a new one — only the
  assigned rep and active status can be edited in place on an existing rule.
- This only affects **new** leads going forward (or a still-unowned lead, for
  the VA-Paid rule). To move an already-assigned lead, use Reassign on the
  lead itself, or **Reassign All** below — see the Sales guide for the
  single-lead action.

## 16b. Lead Generation productivity — priority sort, Needs Attention, speed-to-lead
Lead Generation (2026-08-13) got a pass aimed at helping Sales/Telecaller
close leads faster, and giving you visibility if they don't.

**Priority sort (default):** the list no longer sorts by newest-first —
it ranks in strict tiers, each one always above the next regardless of AI
score: **overdue follow-up** first (a broken promise to the client),
**due today** next, then **everything else open** — ranked primarily by
AI score, so your hottest leads are always visually on top — with a
small nudge for a New/Contacted lead that has no follow-up scheduled yet,
so it doesn't sit forgotten forever. That nudge is deliberately small (it
can only break a near-tie between similarly-scored leads) — it can never
push a stale, mediocre lead above a genuinely hot one (fixed 2026-08-31,
after 3-week-old AI-45 leads were briefly outranking fresh AI-72 leads).
Switch to **Newest** via the sort toggle above the list if you
specifically want chronological order.

A **Lost or Converted lead always sorts to the bottom**, below every open
lead, regardless of its AI score — there's nothing left to follow up on,
so a lead that scored well before it died no longer crowds out live leads
at the top of the list (fixed 2026-08-31).

**"Needs attention today" strip:** five clickable counts above the list —
overdue follow-ups, follow-ups due today, Hot leads (AI score ≥ 70)
nobody's even set a follow-up on yet, **unresponsive** (3+ logged call
and/or outbound WhatsApp attempts, never a connected call or an inbound
reply, and still open — a lead called or messaged repeatedly with total
silence no longer looks identical to one reached on the first try), and
**status may need updating** (a lead still marked New that already has a
note or a logged call against it, from before the "This was a call" note-
box checkbox existed or from a rep typing a plain note without ticking
it — see the Sales guide, Section 1). For a Sales viewer this is scoped
to their own (or unowned) leads; for a Telecaller viewer it's scoped to
just the leads assigned to them (their own separate Telecaller field, see
below) — both are personal worklists. Admin and Manager see the whole
picture, unscoped. Each count links straight to the filtered list.

**Telecaller assignment (2026-09-03):** a lead has two independent
assignments — the Sales **Owner** and a separate **Telecaller** — each
routes to a different person and each auto-assigns on lead creation via
its own least-loaded round-robin (Telecaller has no campaign/service
routing rules the way Sales does — plain round-robin only). A Telecaller
only ever sees/updates leads where they're the assigned Telecaller,
regardless of who owns it. Change either field from the lead's own Edit
page; there's no bulk "Reassign All" for Telecaller the way there is for
Sales owners (Section 16a above covers the Sales-only campaign/service
routing rules — Telecaller isn't part of those). Before this date, every
telecaller worked from one shared, unowned queue — if a lead you're used
to seeing looks missing, check who it's assigned to as Telecaller now.
A new **Telecaller** filter dropdown on the list (next to the existing
Owner filter) lets Admin/Manager see any one telecaller's whole queue at
a glance. Unlike the Sales round-robin (which only ever considers someone
whose PRIMARY role is Sales), the Telecaller round-robin also picks
someone who holds Telecaller as an **additional** role — on this system
nobody's primary role is actually Telecaller; it's granted as an add-on to
an existing Accounts/Intern/etc. staff member who also does calling duty.

**One-time backfill only covered open leads, not old history:** when this
launched, a one-off script assigned every then-**open** lead (New/
Contacted/Qualified) to a telecaller round-robin, so nobody started with
an empty queue — but a lead that was already **Converted or Lost before
2026-09-03** was deliberately left with no Telecaller assigned (a closed
lead isn't part of anyone's active calling queue). That means those older
closed leads are invisible to every Telecaller specifically, though
Admin/Manager still see them as always. Owner confirmed (2026-09-03) this
is fine as-is — not backfilled, since closed leads aren't worth assigning
retroactively. If a telecaller asks where an old converted/lost lead
went, this is why — check it yourself or point them to Admin/Manager.

**Unresponsive lead guidance (2026-09-03):** an unresponsive lead is
already correctly at Contacted, not New — Contacted only ever means an
attempt was made, not that it succeeded. Open one and a new amber
**"📵 Not responding — next best action"** box tells the rep exactly what
to try: switch channels if only one's been attempted (calls-only →
try WhatsApp; WhatsApp-only → try calling, with the historically best
time to call appended when there's enough real connect-rate data), or —
once both channels are genuinely exhausted — points to the
**✨ Suggest a status** button, which reads the lead's own notes/calls
and can now suggest Contacted, Qualified, **or Lost** (a genuinely
sustained, multi-channel, zero-response pattern is a real signal toward
Lost; one or two unanswered calls on a single channel is not). This same
Suggest-a-status button also covers a stale-New lead flagged above.
Nothing ever changes automatically — the rep always picks from the
dropdown (AI's pick pre-selected, overridable) and clicks Apply.

**Status cards (top of the list) are clickable** — click New/Contacted/
Qualified/Converted/Lost/Total to filter to it, same idea as the Needs
Attention counts. The **Converted** card also shows a small **"X Won"**
sub-count — Converted only ever records that the lead became a real Deal,
independent of whether that Deal was later Won, Lost, or is still being
negotiated (a Lead never reverts out of Converted once it gets there,
even if its Deal is later Lost — that's intentional, see the incident
note below). A **deal stage** filter (added 2026-09-02, alongside the
other list filters) lets anyone pull up exactly which Converted leads are
sitting at a given stage — e.g. every one stuck at Negotiation — instead
of having to open each one to check. Each Converted lead's own row also
shows the same info as a small caption under its status badge — "Deal:
Won", "Deal: Won (partial payment)", "Deal: Negotiation", "Deal: Lost",
etc. — colored green/red for the two terminal outcomes.

**Real incident this closed (2026-09-02):** the owner spotted a
"Converted" lead still sitting under its old status weeks after real
conversion, and separately noticed several genuinely-converted leads
(quotation not yet approved) reading as if they were already Won. Root
cause: the Edit Lead form's status dropdown never offered "Converted" as
an option (by design — Converted is only ever reached via the Convert
action), so saving *any* edit on an already-converted lead — even just
fixing a phone number — silently reset its status to whatever the
dropdown happened to submit. Fixed: the status field is now read-only
once a lead is Converted, and the backend rejects any attempt to move it
away from Converted through this form. 24 real leads that had already
drifted this way were found (by scanning for a converted_customer_id/
converted_deal_id with a non-Converted status) and corrected back.

**Overdue/Due today badges** now show right on the list, next to the AI
score badge, so urgency is visible without opening a lead.

**Speed-to-lead reminders:** contacting a lead within minutes rather than
hours meaningfully improves conversion, so a brand-new lead nobody's
engaged with (no note, call, or edit) now gets its **owner reminded after
20 minutes**, and **escalates to you (Admin/Manager) after an hour** if
it's still untouched — a bell notification either way. Runs automatically
every 5 minutes (`app:escalate-untouched-leads`); no configuration needed.
"Engaged with" means any note, logged call, or edit on the lead — adding
any of those clears it from consideration immediately.

**Stalled-lead escalation (any open lead, not just brand-new):** separately,
the daily stagnation check (below) also escalates to you if a lead that
was once worked has since gone cold. An open lead (New/Contacted/
Qualified) with no note, call, or edit for **7 days** emails its owner
daily until it's touched again; if it's *still* untouched **3 days after
that** (10 days total), every active Admin/Manager also gets a bell
notification — a different failure mode from a brand-new lead nobody ever
started on, so it's a separate alert with its own wording ("Lead stalled —
no activity in N+ days"). Both thresholds are `app:send-stagnation-alerts`
command options (`--lead-days`, `--manager-days`), not an admin-editable
setting — change the scheduled command in `routes/console.php` to adjust
them.

**Reassign All (bulk handover):** filter Lead Generation by **Owner**
(the filter row) and, if you can reassign (Admin/Manager), a panel appears
showing how many open leads that person has, with a one-click **Reassign
All** to move every one of them to someone else at once — e.g. covering a
colleague's leads for the day. Pick a reason (On leave / Left the company /
Rebalancing workload / Other); the new owner is notified and a note is left
on each lead. This is a plain one-time move, not temporary — reassign back
the same way later, same as the single-lead **Reassign** button (Sales
guide). This is the same mechanism the Users page's deactivation handover
(Section 9) uses when a leaving Sales user still owns open leads.

**Reassignment Analytics report** (Reports panel, Admin/Manager) — every
handoff above (single, bulk, and the deactivation handover — one shared
mechanism) is logged with its reason, but had never been reported on
until now. Per active Sales rep, for a chosen month: how many leads were
reassigned away from them (with a reasons breakdown), and how many were
reassigned to them. Deliberately simple — a single table, no multi-cut
breakdown. Export CSV.

**Rep Win Rates report** (Reports panel, Admin/Manager) — win rate
(Won ÷ (Won + Lost)) per Sales rep for a chosen month, broken down by
lead source and by the originating lead's score band. Recorded
automatically on the 1st of each month for the month that just ended
(`app:snapshot-rep-win-rates`, configurable via
`REP_WIN_RATE_SNAPSHOT_CRON`/`REP_WIN_RATE_SNAPSHOT_ENABLED` in
`.env`) — the page only ever shows what's already been recorded, so a
month with no snapshot yet shows a plain "not recorded yet" message
rather than a live-computed number. **This is measurement only**: the
figure isn't used anywhere in lead routing or assignment today — it
exists so there's a real trend to look at once enough of it has
accumulated, not to quietly change how leads get routed.

## 17. AI features (optional)
Several AI helpers are built into the CRM, powered by Anthropic's Claude. They are
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

**Score Calibration report** (Reports panel, Admin/Manager) — the honest
answer to "is the 0-100 AI score actually predictive of outcome?" Buckets
every closed lead (Converted or Lost, by when it closed) into the same
Cold/Warm/Hot bands as the score badge, and shows conversion rate plus
average/median time-to-close per band. If Hot leads aren't converting
meaningfully more than Cold ones, this report is built to show that
plainly rather than hide it — it's measurement only and never adjusts
scoring on its own. Filterable by month; exports to CSV. A **Trend over
time** table below the main view is recorded automatically on the 1st
of each month (`app:snapshot-score-calibration`, configurable via
`SCORE_CALIBRATION_SNAPSHOT_CRON`/`SCORE_CALIBRATION_SNAPSHOT_ENABLED`
in `.env`) — a running history so calibration drift is something to
actually look back at, separate from the always-current on-demand view
above it.

**Lead auto-assignment** — a new lead with no owner (e.g. from the website
form) is automatically assigned to whichever active Sales user currently owns
the fewest open leads, so leads never sit unowned. This runs independently of
`AI_ENABLED` — it's routing, not an AI feature.

**Draft follow-up / Draft reply (✨)** — a button on leads, deals, and tickets.
When clicked, Claude reads the record's details and history (for a deal, its
own notes — deals have no call history of their own, only leads do), then
writes a suggested message. The staff member edits it and sends it
themselves. Claude never sends anything automatically.

**AI-suggested Lost reason** — moving a Deal to Lost (drag-and-drop on the
Sales Pipeline board, or the Stage field on the deal's own page) briefly
checks its notes — and its originating lead's notes/calls, if it was
converted from one — for a suggested reason, pre-highlighted with a
one-line "why" underneath. It's always just a starting point: the rep
picks any of the 5 options with no extra friction, and a deal with too
little history simply shows the plain picker with nothing pre-selected —
never a guessed default.

**Loss Reasons report** (Reports panel, Admin/Manager) — a real-numbers
answer to "why are we actually losing deals," broken down by reason
alone, by reason per rep ("Loss reasons by rep" — a coaching signal, not
a ranking), by reason per lead source, and by reason against the
originating lead's AI score band (Cold/Warm/Hot, the same bands shown on
every lead's score badge) — so a pattern like "high-scored leads mostly
lost to going dark" stands out from "high-scored leads mostly lost to a
competitor." Also shows how often a rep accepted vs. overrode the
AI-suggested Lost reason above, as a running check on whether that
suggestion is actually useful. Filterable by month; exports to CSV. Pure
aggregation over what's already recorded — it doesn't call Claude and
never changes.

**Automated lead nurture follow-ups** — daily at 10:30 IST, any New lead its
owner hasn't personally added a note or logged a call on gets an AI-drafted
follow-up at day 1 (first outreach), day 3 (gentle nudge), and day 7 (final,
low-pressure check-in) since it came in. Each draft lands as a staff-only
note on the lead plus a bell notification to the owner — never sent
automatically. The system-generated note created from the original enquiry
(website form / WhatsApp message) doesn't count as a staff touch, so a lead
that's never actually been worked still qualifies. Skips Sundays, same as
the stagnation alerts.

**Automated deal stall check-ins** — daily at 10:35 IST, any open deal
(not Won/Lost) with no note or logged edit in **7 days** gets an
AI-drafted check-in, landed the same way as the lead nurture drafts above
(a staff-only note plus a bell notification to the owner, never sent
automatically). Unlike the lead version's fixed day-1/3/7 cadence — which
never fires again once a New lead is touched even once — a deal here can
go quiet, get worked, then go quiet again any number of times: each
genuinely new note or edit resets the clock, so it can draft another
check-in the next time 7 days pass with nothing happening. Skips Sundays.

**Summarize** — a button on client pages, deals, and tickets. Claude reads the
full timeline (notes, and calls/interactions where the record has them) and
produces a short paragraph summarising the situation. Useful when picking up
a colleague's account.

**Festival greeting drafts** — every morning, for clients with an active Social
Media or GMB project, Claude drafts a festival greeting caption 7 days ahead of
each entry in the **Festivals** calendar (Section 7) and adds it to that
project's Content Collaboration queue as a draft. A team member always reviews
and approves it before anything is scheduled or published.

**AI daily-priorities digest** — every staff member with anything due gets a
short AI-written "here's your day" line at the top of their 9 AM morning digest
email, which also appears as a dashboard banner for the rest of that day. It's
generated only from that person's own tasks/follow-ups.

**AI weekly owner digest** — every Monday at 9 AM, Admin/Manager get a short
AI-written paragraph synthesizing the week ahead: open pipeline, MRR,
cash expected this month and over the next 3 months, receivables
outstanding (including the 90+ days overdue figure), how many clients
Client Radar has flagged (and why), and the Visibility Audit funnel for
the last 7 days (new Meta leads tagged GMB, invited, viewed the offer
page, reached checkout, paid). It's a synthesis of the existing
Business Overview, Cash Forecast, Client Radar, and VA Recovery
reports — the email links to all of them — and also appears as a
dashboard banner for the rest
of that Monday. The email/summary paragraph is skipped if AI is turned
off, since there's nothing to synthesize beyond what those three reports
already show on their own — but the underlying numbers are recorded
every Monday either way (see Weekly Digest History below), so trend
tracking never has a gap just because AI happens to be off that week.

**Weekly Digest History** (link on the dashboard, next to "Your week
ahead") — Admin/Manager only. The dashboard banner above only ever shows
*this* Monday's digest; this page keeps every past week's snapshot (the
same numbers, plus the AI summary when one was generated) so you can see
whether MRR, receivables, and flagged-client count are trending up or
down, instead of the figure being overwritten and lost every Monday.
Includes a trendline chart for the last ~12 weeks.

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

**Staff productivity ranking (Score/Rank/Focus area + ✨ Suggest Improvements
for the Team)** — the same **Employee Performance Report** ranks each person
against others in their own role only (Sales vs Sales, Support vs Support,
etc. — never across roles, since the numbers mean different things per job).
A role with fewer than 2 people shows "Not enough peers yet to compare"
instead of a rank; Admin/Manager themselves are never ranked. Click **✨
Suggest Improvements for the Team** to fill the Focus area column with a
short, encouraging, specific suggestion per person, grounded only in their
own numbers — same visibility rule as the AI Summary above (Admin/Manager
only). Each employee separately sees only their own rank and can request
their own tip, privately, from their own Dashboard — see the relevant
role guide (e.g. `sales.md`, `support.md`) for what that looks like.

**Client Radar suggestions (✨ Suggest action)** — on the **Client Radar** page
(Section 2), a button per flagged client that has Claude suggest one concrete
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

**Call Log voice notes** — on the **Log a Call** form, staff can record a
short voice note (Hindi, Marathi, English, or a mix — no need to stick to
English like the browser Dictate button requires) instead of typing. Google
Speech-to-Text transcribes it, then Claude translates/cleans the result into
natural English. The translated note appears as a separate "🎙️ Voice note"
block under the call's typed notes on the **Calling** page, usually within a
minute (shown as "🎙️ Transcribing…" until then) — the rep's own typed notes
are never overwritten. If either step fails (bad audio, no speech detected,
API outage), the row just shows "Transcription failed" instead of blocking
anything. This is one of two AI features here that depend on a second vendor
besides Anthropic — see below.

**Create Meeting and Meet notes (optional, separate integration from voice
notes above)** — since 2026-07-25 this is a single company-wide Google
connection, not per-user: **you** (an admin) connect NEDS's own Google
account once from **Profile → Google Account** — this section is
admin-only, nobody else sees it. Once connected, **every** staff member
gets a **Create Meeting** button on any client or lead's Calls tab — it
creates a real Meet-enabled Calendar event through your connection, invites
the client/lead's own email (Google emails them the invite directly), and
shows the generated link back to whoever clicked it, to share directly if
needed (e.g. over WhatsApp). Nobody else ever needs to connect their own
Google account. **Import Meet Notes** still exists for a call that happened
outside that flow (ad hoc, or from before this change) — it pulls in the
recording link, transcript link, and full transcript Google Meet already
generated for it, searching your connected account's Calendar rather than
the viewing staff member's own. If AI is also enabled, an imported
transcript gets summarized into short "Key points / Decisions / Action
items" notes, persisted so anyone who opens that client/lead page sees the
same summary — either generated automatically shortly after import, or via
a **Summarize with AI** / **Retry** button.

Since recordings and transcripts are tied to whoever's account organized
the Calendar event, and every meeting created through the CRM is now
organized by **your** connected account, they'll all land in your own
Google Drive going forward — that's expected, not a bug, and is what makes
"one shared inbox for every client meeting" work. A **Sync recording &
transcript** button appears on a meeting created via Create Meeting once
it's happened, to pull in whatever Google's finished processing for it.

**To turn on:** add these lines to the server `.env`, then run
`php artisan config:cache`:
```
AI_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...
```
If the features are off, the buttons/drafts/digests simply don't appear and
nothing else changes. Usage is billed by Anthropic per request (very low cost
for this volume).

Call Log voice notes additionally need a Google Cloud Speech-to-Text API key
(separate from Anthropic, its own billing):
```
GOOGLE_SPEECH_API_KEY=...
```
Without this key, the **Record voice note** button doesn't appear even if
`AI_ENABLED=true` — every other AI feature above works normally either way.

Create Meeting / Meet notes need their own Google Cloud OAuth app (Calendar +
Drive APIs) — set up once in Google Cloud Console, then add:
```
GOOGLE_MEET_ENABLED=true
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
```
Without `GOOGLE_MEET_ENABLED=true` (or a missing client id/secret), the
**Connect Google Account** section on Profile (admin-only) and the
**Create Meeting**/**Import Meet Notes** buttons don't appear, even if
`AI_ENABLED=true`. The AI-summary step on top of an imported transcript
additionally needs `AI_ENABLED=true` — with only `GOOGLE_MEET_ENABLED` on,
creating/importing meetings still works, it just won't summarize.

**Scope note (2026-07-25):** the OAuth scopes now include Calendar
**write** access (`calendar.events`), not just read, since Create Meeting
needs to insert a new event — a step up from Phase 1's read-only design. If
you connected before this date, disconnect and reconnect once from Profile
so Google re-prompts for the new scope (`prompt=consent` is always forced,
so this happens automatically on reconnect).

## Tip
Adding a new module/menu item or changing a label is a code change that deploys
automatically — but the menu lives in the database, so after such a change the
menu must be re-seeded on the server (`php artisan db:seed
--class=MenuItemsSeeder --force`).
