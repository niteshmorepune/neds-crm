# Manager guide

Managers see the **full company dashboard** and the **management reports**, plus
every operational module for oversight.

> Managers are required to use **Two-Factor Authentication** — see
> [Getting Started](getting-started.md) if you haven't set it up.

## The company dashboard
Your dashboard shows the whole business at a glance:
- **Stat cards** — Total / Active / Inactive **Clients**, **Total Leads**, and
  **Tasks Overview**, each with the % change vs last month.
- **Services Overview** — a donut of projects by service line (SEO, GMB, Website,
  Social, Ads…).
- **Task Summary** — Assigned / Pending / Overdue / Completed.
- **My Meeting Invitations** — if a colleague has scheduled a Google Meet call
  and added you as an internal attendee (see **Create Meeting** below), it
  shows here with **Accept**/**Decline** buttons and a direct **Join Google
  Meet** link, so you don't have to open the client's page just to see it.
- **Link panels** — quick access to Daily Reports, the Project Dashboard, and the
  Reports below.

A separate **🤖 AI Recommendations** section (below the daily "Today" list,
Admin/Manager only) is where the **weekly owner digest** paragraph lives
(Monday mornings, if AI is enabled) — pipeline, MRR, cash expected, overdue
receivables, and how many clients Client Radar has flagged, plus a link to
the Employee Performance report's own coaching suggestions and productivity
flags. It's a dedicated section now rather than mixed in with daily-habit
items like leave approvals and festival greetings.

## Resources
**Resources** in the sidebar has two tabs — **Files** (a shared internal
file library: plugin builds, certificates, templates) and **Links** (a
company-wide reference list: hosting panel, domain registrar, and anything
else the team needs quick access to). Everyone can view what their role can
see; you (and Admin) can add, edit, or remove entries on either tab. Each
entry can optionally be tagged with a category/department and, separately,
restricted to specific roles ("Visible to") — leave that blank to show it to
everyone, which is the default. You and Admin always see every entry
regardless of that setting. The lists can be filtered by category/department
— useful once either grows past a handful of items.

Every client's own page also has a **Links** tab for that specific
client's website, Google Business Profile, socials, Drive folder, and
similar — anyone who can open the client's page can see these; editing
them follows the same access rule as everything else on that page.

## Reports
From the **Reports** panel on the dashboard:

**Business Overview** — the executive snapshot, for a chosen financial year:
- **Partner Performance**: which partners referred which clients and how much
  won/pipeline/lost business is attributed to each. Click a partner's name to
  open their page (see **Partner client health** below) — Accounts sees the
  name as plain text instead, since Partner records themselves are
  Admin/Manager only.
- **AR Aging**, **MRR / Recurring Snapshot**, and **Client Concentration**
  (% of revenue from your top 5/10 clients): as a manager you see the summary
  numbers here (e.g. total outstanding, total MRR, the concentration
  percentages) but not the itemized invoice-level or named-client detail —
  that's limited to Admin/Accounts. The **Total outstanding** tile itself is
  clickable — it opens the Receivables Report directly.
- **Pipeline & Funnel**: company-wide open pipeline by stage, win rate, avg
  deal size, and avg sales-cycle length for the period — full detail, same as
  Admin. Export CSV.

**Cash Forecast** (linked from Business Overview, or the dashboard's Reports
panel) — a near-term cash view, blending:
- **Recurring expected**: active recurring-invoice templates projected
  forward month by month.
- **Receivables due**: already-invoiced amounts bucketed by due month
  (anything already overdue collapses into the first month — it's owed now
  regardless of the original date).
- **Weighted pipeline**: shown separately and clearly labelled indicative —
  deals don't have an expected-close date, so it isn't blended into the
  monthly chart as if it were committed cash.

**Employee Performance Report** — per person, for a chosen month:
- tasks completed, **on-time %**, calls made, leads converted, **attendance %**,
  and daily reports submitted.
- Pick the month, then **Export CSV** for records or appraisals.
- Click **✨ Generate AI Summary** for a narrative read of the table — trends,
  standouts, and anyone whose numbers suggest they may need support. For a
  Sales rep, once there's enough pipeline history it names specific stages
  where their deals move slower than the team average (e.g. "averages 18
  days in Negotiation before moving a deal on, against the team's 9 days")
  instead of a vague "needs support" line — this may not appear yet on a
  newer pipeline. It's generated only from the numbers already on the page
  (never invents a reason behind them) and is **visible to Admin/Manager
  only** — it's never shown to the employee it's about, so use it as a
  starting point for a conversation, not as a rating you show them.
- **Score and Rank columns**: each person is ranked only against their own
  role (Sales vs Sales, Support vs Support, etc. — comparing across roles
  wouldn't mean anything) using the same numbers already in the table, with
  a **Focus area** showing their single weakest metric this month. A role
  with fewer than 2 people shows "Not enough peers in this role yet to
  compare" instead of a rank — there's nobody to compare against. Admin and
  Manager rows are never ranked (they're not participants). Click **✨
  Suggest Improvements for the Team** to fill the Focus area column with a
  short, specific, encouraging suggestion per person — same "never invents
  a reason" rule as the AI Summary above, and same visibility (Admin/Manager
  only; each employee separately sees only their own suggestion, on their
  own Dashboard — see `getting-started.md`).
- **Trend indicators**: every number column shows a small colored `(+3)` or
  `(-2)` next to it, comparing the selected month against the one right
  before it — green for an improvement, red for a drop. A blank means there's
  nothing to compare (e.g. no attendance records yet last month, or the
  person joined this month). This is the same underlying figures as the
  table itself, just diffed month over month — no new data.

**Revenue Report** — for a chosen financial year:
- income **by month**, **by service**, and **by client**, split **recurring vs
  one-time**. Export CSV.

**Lead Source Performance** — for a chosen month:
- leads captured, converted, and conversion rate **by source** (Website,
  WhatsApp, Referral, Cold Call, Phone Enquiry, Other).
- for website leads with UTM tracking parameters, a second table breaks the
  same numbers down **by campaign** (e.g. "google / cpc / seo-pune-2026") —
  this is what tells you which ad or link is actually worth the spend.
- **Won value** per source/campaign only counts deals that actually closed
  Won, not just leads that converted to a client and are still in the
  pipeline. **Avg AI score** shows if one channel tends to bring in
  higher-quality enquiries. Export CSV.
- Both the **Leads captured** tile and each **By source** row link straight
  through to **Lead Generation**, pre-filtered to that exact source and the
  same month you're looking at — so "how many of these actual leads came
  in" is one click away instead of a manual filter.

**AI Usage Report** — for a chosen month, which of the CRM's AI features are
actually being used: calls per feature (lead scoring, draft replies, monthly
wins notes, and the rest), tokens processed, and a rough estimated cost per
feature and in total. The cost is an estimate from a configured rate, not a
bill — useful for spotting which features are worth the spend and which
nobody's touching. A **Feedback** column shows Helpful/Not helpful counts
from anyone who's clicked the rating prompt after a draft — a quality
signal alongside the raw call count, since a feature getting used a lot but
rated poorly is worth a different conversation than one nobody's touched
at all. Export CSV.

**Ask the CRM** — type a business question in plain English ("Which
clients are at risk this month?", "What's our win rate on SEO deals?")
and get an answer grounded in real figures from the reports above —
never invented. If it doesn't recognise the question, it lists what it
can currently answer instead of guessing. Every answer links to the full
report behind it, and shows the exact figures it was grounded in, so you
can double-check the numbers yourself.

**Outstanding Receivables** — what clients still owe (shared with accounts).

**Collections** — a client-by-client follow-up worklist (shared with accounts):
recurring clients who haven't paid, invoices only partially paid, payment
promises that have been broken, and for milestone-billed projects whether the
next milestone is marked **Ready to invoice**. Filter by **All clients**,
**Direct clients**, or one specific partner. Open any partner's page (below)
to see the same view already scoped to them.

**Contract & Renewal Dashboard** (sidebar → **Contract & Renewals**, shared
with Accounts and Sales) — the fuller version of the 30-day renewal bell
notification: active recurring contracts ending in the next **30, 60, or 90
days** (toggle between the three), each with a **renewal status** you set
yourself — Not Started → Discussion → Proposal Sent → Negotiation →
Renewed/Lost — so the team can see at a glance who's actually had the
renewal conversation and who hasn't. Filter the list by status using the
colored chips at the top. The **MRR at stake** tile totals the monthly-
equivalent value of every contract in the selected window.

**Weekly digest history** — see [Email alerts](#email-alerts) below for the
Monday digest itself; the history page keeps every past week's snapshot
with trendlines so you can see whether MRR, receivables, and flagged-client
count are trending up or down, not just this week's figure.

## Oversight
You have access to Leads, Deals, Quotations, Invoices, Projects, Tasks, Tickets,
Clients, Attendance, Calls and Daily Reports so you can monitor and step in
anywhere. Use the **search bar** to jump to any record.

**Ticket escalations:** anyone on Support (or Sales, for their own clients)
can click **🔺 Escalate to managers** on a ticket to flag it for you
specifically — separate from the automatic SLA-breach email. You get a bell
notification, the ticket shows an orange "Escalated" badge, and it stays
that way (visible via the **Tickets → Escalated** filter, or the count on
your **Action Center**) until you click **Clear escalation** on the
ticket's page — only Admin/Manager can clear one.

**Quotation approvals:** every new quotation a Sales rep (or anyone) saves
now needs your approval before **Send to Client** unlocks — see **Central
Approval Center** below for the queue, or **Approve**/**Reject**/**Request
changes** directly from the quotation's own page.

**Client 360° summary strip:** every client's profile page now opens with a
row of tiles — **MRR** (active recurring services), **Next Renewal** (the
soonest end date among them), and, for roles with invoice access,
**Total Revenue** (lifetime) and **Outstanding** (clickable — jumps
straight to that client's Invoices tab). The Health Score badge next to
the client's status (see Client Radar above) sits right alongside these.

**Seeing who services which client:** open any client's profile and click the
**Services** tab. The **Projects** table now has a **Team** column showing the
Lead and any additional team members for each project/service. This is the
fastest way to answer "which of our team handles GMB for ABC Corp?"

Every project also has a **Project Manager** (set on the project's Edit page)
who is ultimately accountable for it — a different person can be the Project
Manager on every project, and one person can be the Project Manager on as
many projects as needed.

**Project Updates → My Services:** the Project Updates page has a new **My Services**
toggle that filters the list to only projects where you are the Lead or a team member.
Useful for managers who also run their own service accounts.

**Create Meeting and Meet notes (optional):** once an admin has connected
NEDS's Google account once (**Profile → Google Account**), anyone on the
team can click **Create Meeting** on a client or lead's **Calls** tab to
schedule a real Google Meet call through that same connection — it invites
the client automatically and shows the link to share directly too, no
personal Google account needed per staff member. **Inviting teammates:**
the scheduler also has a multi-select of active staff — anyone you add gets
a CRM bell notification, a card on their own Dashboard's **My Meeting
Invitations** widget with **Accept**/**Decline** buttons, and a direct
**Join Google Meet** link, without needing to be a real Google Calendar
guest. Each meeting's own page shows every invited teammate's current
status (Pending/Accepted/Declined) next to the client's own name. For a
call that already happened outside that flow, **Import Meet Notes** pulls
in the recording link, transcript link, and full transcript Google Meet
already generated — shown the same way a logged call is. If AI is also
enabled, an imported transcript gets a short **AI summary** (Key points /
Decisions / Action items), visible to anyone who opens that client or
lead's page, including you.

## Sales Dashboard
A company-wide view (Sidebar → **Sales Dashboard**, under Sales Department) —
same KPI strip/stage-conversion/trend/needs-attention sections described in
the sales guide, but unscoped (everyone's deals). Two sections are
Admin/Manager only:

- **Rep leaderboard** — per Sales rep: pipeline value, won this month, target
  this month, % to target, win rate, avg deal size.
- **Save targets** — set the company's monthly and financial-year revenue
  target, and each rep's individual monthly target, right from the
  leaderboard row. Leave a field blank to leave that target unchanged — it
  will never zero out an existing target you don't touch. Targets are
  optional; nothing breaks if none are set, the progress bars just read "No
  target set". A blank monthly field (company or a rep's own) shows a
  **Suggested: ₹…** hint underneath — the trailing 3 months' average won
  value plus 10%, click it to fill the field, or ignore it and type your
  own number. It only appears once there's enough recent history to base it
  on, so it may not show yet for a newer pipeline; there's no suggestion for
  the financial-year target.

## Incentives
Sidebar → **Incentives**. Admin/Manager see every active Sales rep's live
monthly incentive (sales this month, current slab, individual incentive,
team bonus, total) computed from the same Won-deal figures as the Sales
Dashboard, plus:

- **Team bonus pool** — the fixed rupee amount, split evenly across active
  Sales reps, paid out in any month the company-wide monthly target (set on
  the Sales Dashboard) is met. Edit and save it here; it applies from the
  current month onward.
- The company target itself is still set on the **Sales Dashboard** — this
  page only shows target-vs-actual and links there to change it, so there's
  one place that writes it.

Numbers shown here for the current month are a live estimate — nothing is
locked in until the 1st of the following month, when a scheduled job snapshots
each rep's just-ended month into a permanent record. That means an old deal
edited after month-end never changes a past month's payout figure.

## Manager Action Center
Sidebar → **Action Center** — one page rolling up everything across the CRM
that needs your attention right now, instead of checking five separate
pages: overdue tasks (team-wide), clients Client Radar has flagged, overdue
invoices, tickets whose SLA has actually breached (not just approaching),
contracts renewing within 30 days, and pending follow-up reminders (listed
inline, since there's no dedicated page for those yet). Every count links
straight through to the real page behind it — Tasks, Client Radar, the
Receivables Report, Tickets, or the Contract & Renewal Dashboard — so
clicking a number always shows you exactly what's in it. **Stagnant deals**
aren't in this list yet — there's no reusable "hasn't moved" definition
built for that in the CRM today, only the stagnation *alert emails*
(see below), which are a different thing.

## Central Approval Center
Sidebar → **Approval Center** — every pending approval decision across the
CRM in one place, so you don't have to check Leave Requests, Quotations,
and each project separately:

- **Leave Requests** — **Approve** or **Reject** (with an optional reason),
  exactly like the dedicated Leave Approvals page.
- **Quotations** — new quotations wait here for your decision before they
  can be sent to a client. **Approve** clears it to send. **Reject** (with
  an optional reason) or **Request changes** (a reason is required, so the
  creator knows what to fix) send it back — the creator can edit and click
  **Resubmit for approval** to put it back in your queue.
- **Project Updates** — AI-drafted client updates waiting on a project
  owner's review before they reach the client (edit if needed, then
  **Approve & Send**, or **Discard**) — the same review you'd see on that
  project's own page, just surfaced here too.

Only genuinely *pending* items show up — once you act on something, it
drops off this page and the count at the top updates. Two things the team
asked about were deliberately left out: **Content** approval already
happens entirely inside SMDost before it ever reaches this CRM, so there's
nothing real to approve here; and **Client Requests** isn't a concept that
exists anywhere in the CRM yet (clients raise **Tickets**, not a separate
"request") — building that would be a new feature, not something to
aggregate.

## Team Workload & Capacity
Sidebar → **Team Workload** — every active Sales/Support/Accounts/Intern/
Telecaller person's open (not-Done) task count and overdue count, grouped
by role with each role's own average shown at the top of its group. A
person is flagged **Overloaded** when their open task count is more than
1.5× their role's average, or they have 3 or more overdue tasks — the
overdue check exists specifically so a lone person in a role (nobody to
average against) still gets flagged if they're genuinely behind. Someone
with zero tasks shows up too — an empty plate is worth knowing about, not
just an overloaded one. Click a name to see their full task list.

## Project Health Dashboard
Sidebar → **Project Health** — every active or on-hold project, scored:
- 🔴 **Red** — the deadline has passed.
- 🟠 **Orange** — the deadline is within 7 days and completion is under 80%,
  or the project has any overdue task at all (this one applies even to a
  project with no deadline set).
- 🟡 **Yellow** — completion is under 50% and more than half the project's
  own timeline has already gone by.
- 🟢 **Green** — everything else, including a project with no deadline set
  that isn't carrying any overdue tasks.

A project with no tasks logged yet is treated as 0% complete for this
scoring — deliberately, so a project that's gone quiet doesn't slip through
for lack of data. Completed projects aren't shown — nothing left to track.

## Revenue at Risk
Sidebar → **Revenue at Risk** — one page pulling together three figures
that otherwise live on three separate reports: **overdue receivables**
(every AR-aging bucket except not-yet-due), **MRR renewing in 30 days**
(same figure as the Contract & Renewal Dashboard's own tile), and **MRR
tied to Client-Radar-flagged clients**. The headline total is a plain sum
of the three and isn't deduplicated — a client can be both overdue on an
invoice and Client-Radar-flagged at the same time, so it can appear in two
buckets at once. Click any bucket to see exactly what's behind that
figure, on the report it came from.

## Client Radar
A **Client Radar** sidebar item (and a dashboard banner when clients are
flagged) surfaces active clients worth a proactive check-in:
- **No Contact** — no note, call, or ticket in the last 14 days.
- **Declining Activity** — recent touches well below the prior 30 days.
- **Overdue Invoice** — at least one overdue invoice.
- **Growth Opportunity** — only using one service line even though more are
  active — a natural upsell prompt.
- **Low Satisfaction** — at least one ticket rated 2/5 or below by the client
  in the last 60 days (clients rate a ticket once it's Resolved/Closed, from
  the client portal — see `client-portal.md`).

Each flagged client also shows a **Health Score** (0–100, worst clients
first): starts at 100, then loses 30 for No Contact, 20 for Declining
Activity, 25 for Overdue Invoice, and 25 for Low Satisfaction (Growth
Opportunity is a positive signal, so it never costs points). The same
score shows as a badge next to the client's status on their own Client 360
page. Everything is computed live from existing data, nothing is stored.
Click **✨ Suggest action** next to a client to have Claude draft a specific
next step from that client's flags — generated on demand, per client, so
it's never run automatically as a batch.

On a **Low Satisfaction** flag specifically, a second button — **✨ Draft
recovery message** — writes a client-facing apology grounded in the actual
ticket that was rated poorly (not just the generic flag). Review and
personalize it before sending; nothing goes out automatically.

## Employee 360°
Sidebar → **Employee 360°** — one consolidated page per employee: this
month's performance (the same score/rank/focus-area the Employee
Performance report uses), task workload (total/pending/overdue), tickets
currently assigned to them, their last 14 days of attendance, and a
**Manager Notes** panel for feedback, areas of improvement, and follow-up
actions — visible only to Admin/Manager, never to the employee themselves.
This is the same notes feature as the one on the Users → Edit page
(`admin.md` Section 9), just reachable without needing full Users access —
useful if you want to leave a note on someone without also being able to
change their account/role.

## Daily reports & attendance
- **Daily Reports → Team** shows what each person submitted for the selected
  date. Each name also shows a **"X/Y this week"** badge — how many of the
  last 7 days (excluding Sunday) they've submitted a report for — so a
  chronic non-submitter stands out (red = zero this week, amber = partial,
  green = perfect) without checking each day one at a time.
- **Attendance** — use the dropdown on the Attendance page to switch between team
  members and view their monthly record. You can see all employees except admins.
- To correct an entry (e.g. someone forgot to check in), go to **Corrections**,
  pick the date, and update the **status** and **notes**. Times are set by
  the employee's own check-in/check-out and cannot be edited here — corrections
  are logged to the audit trail.
- **Sync from biometric** — click this button on the Attendance page if a
  punch looks missing or a check-in/out time looks wrong. It pulls fresh data
  from the biometric machine within about a minute and shows a status line
  once done. See `admin.md` Section 9a for the full biometric setup/troubleshooting.
- **Leave Requests → Review pending** — any admin or manager can approve or
  reject a leave request (you can't approve your own). The queue shows each
  request's **Type** (Full Day or Half Day) — approving a Full Day request
  marks the employee's attendance as **Leave** for each office day in the
  range (Sundays are skipped), while a Half Day request only marks that one
  day as **Half Day**, not a full day off. Rejecting lets you add a short
  note explaining why.

## Best Employee of the Quarter
**Best Employee** in the sidebar (visible to everyone — Admin/Manager get the
full review queue, everyone else sees only their own past approved awards).
Each financial-year quarter (Apr–Jun, Jul–Sep, Oct–Dec, Jan–Mar), AI reviews
the same numbers already used for Employee Performance ranking — tasks,
on-time %, calls, leads converted, attendance, daily reports — and suggests
a winner per department (Sales/Support/Accounts/Intern/Telecaller) plus one
company-wide "Best Employee of the Quarter." A department needs at least 2
eligible people that quarter to get a suggestion — not enough peers means no
award that quarter, not a fabricated one.

This runs automatically right after quarter close, but nothing is announced
yet — each suggestion lands as a **Pending review** card with the AI's pick
and a draft citation, both editable. As Admin/Manager you can:
- **Approve as-is**, or change the **Winner** dropdown to a different
  eligible person in that department first (the citation textarea stays
  editable either way) — Approve commits whatever's in the form.
- **Reject** if no one should win this quarter.

Approving notifies the winner (bell) and posts the citation to the **Notice
Board** automatically — no separate step. It's recognition only: a
certificate PDF (download link appears once approved), no reward amount
tracked in the CRM. Use **Generate / regenerate** on this page to re-run a
quarter manually (e.g. after a data correction) — an already-approved award
for that quarter is never touched by a regenerate.

## Content collaboration (Partners)

When NEDS works with an external content agency, use the **Content
Collaboration** module to track what has been commissioned, where it is in the
workflow, and when it was published.

### Setting up a partner
Go to **Partners** in the sidebar. Add the agency with a name and (optionally)
email and phone. One partner record covers all the projects you work with them
on.

### Tracking agency-sourced deals
When a deal was introduced to NEDS by a partner agency (rather than the client
coming to us directly), open the deal and set the **Referred by** dropdown to
that agency. This is separate from the content collaboration workflow — it
answers "how did we get this client?" at the deal level, so you can see over
time which agencies are generating business for NEDS. Leave it blank for direct
clients.

### Two workflow types
When adding a content piece to a project, choose the workflow:

| Workflow | What it means |
|---|---|
| **Agency-led** | Agency creates the full content (copy + visuals) and delivers to NEDS. Starts in *Pending from agency*. |
| **NEDS-led** | NEDS writes the copy/brief, sends it to the partner, partner creates images/video and sends back. Starts in *Copy drafting*. |

> **Auto-sync from SMDost:** For NEDS-led pieces, you don't need to add them manually. When the team clicks **Send to agency** on a content piece in SMDost, the CRM automatically creates a *NEDS-led* content piece on the matching project (status: *Sent to partner*), pre-filled with the copy text. Just open it and generate an upload link for the partner — no copy-paste needed.

### Status flow

**Agency-led:** Pending from agency → Received → Approved → Scheduled → Published

**NEDS-led:** Copy drafting → Sent to partner → Received → Approved → Scheduled → Published

Advance the status using the **Move to…** button on the content piece detail page.
When a piece is marked *Published*, the timestamp is recorded automatically.

### Secure partner upload link
Instead of emailing files back and forth, you can generate a secure upload link
for the partner:

1. Open the content piece → click **Generate upload link**.
2. Copy the URL and send it to the partner (WhatsApp, email, etc.).
3. The link is valid for **7 days**. The partner visits the URL, selects their
   files (images, video, PDF), and clicks Upload — no CRM login needed.
4. When they upload, the status automatically advances to *Received* and the
   files appear in the **Attachments** section (marked *Partner upload* in
   yellow).

Only admins and managers can generate upload links. If a link expires before
the partner uploads, just generate a new one — it replaces the old token.

### Google Drive links
You can store a Google Drive link on each project (the shared folder) and also
on individual content pieces (a specific file or sub-folder). These appear as
clickable links on the project page and on each piece — handy when you prefer
Drive over the upload link.

To set the project-level folder link: **Edit project → Google Drive folder link**.

### Monthly volume
Up to 18 content pieces per client per month is normal. Filter the content list
by status or platform using the chips at the top of the index page.

### Festival greeting drafts
For any client with an active Social Media or GMB project, the CRM
automatically drafts a festival greeting caption with AI 7 days ahead of each
festival in the **Festivals** calendar (admin-managed) and adds it to that
project's content queue — look for the 🎉 badge next to the title. Review and
edit it like any other content piece before it goes anywhere; nothing is
posted automatically. If a festival you expect to see isn't showing up,
check with an admin — only fixed-date holidays are pre-loaded, so
lunar/regional festivals (Diwali, Holi, etc.) need to be added each year.

### Partner client health
Click a partner's **name** on the Partners list (or from Business Overview's
Partner Performance table) to open their page. Alongside their contact
details, it shows:

- **Billed — last 6 months**: total invoiced (issued, not just paid) for that
  partner's clients, as 6 month-by-month tiles plus a per-client breakdown
  below. Every referred client appears in the by-client table, even one
  billed nothing in the window — so it can't be mistaken for the (narrower)
  client health table below it, which only lists clients that actually need
  attention.
- **Client health**: the same collections/delivery table as the Collections
  report (above), already filtered to that partner's referred clients — who
  hasn't paid, who's only partially paid (with the oldest overdue invoice's
  age shown in both days and an approximate month count), and how each
  active project is progressing.

Useful when a partner asks "how are my clients doing with us" or "what have
we billed through you lately," or before a check-in call with them.

## Notice Board
**Notice Board** in the sidebar (Admin/Manager only) is for posting time-bound
company notices — office closures, holidays, policy changes, service updates
— without relying on email or WhatsApp. Each post has a title, message,
**audience** (Staff only, Clients only, or both), a start time, and an
optional end time (blank = standing notice, no expiry); check **Pin to top**
to keep one above newer posts.

A Staff/Both post shows as a dismissible 📣 banner at the top of everyone's
Dashboard; a Clients/Both post shows the same way on the Client Portal home
page. Nothing to manually take down — a post stops showing once its end date
passes, and only shows once its start time arrives.

## Team Nudges
**Team Nudges** in the sidebar (Admin/Manager only) is for assigning a
targeted, trackable reminder to a specific role (or everyone) — unlike a
Notice Board post, which is just a broadcast. Set a **title**, optional
**description**, **target** role, **recurrence** (One-time or Weekly), and —
for weekly nudges only — an optional **auto-detect** check that clears the
reminder for someone automatically once they've done the real thing (e.g.
logged a Ticket), no manual click needed.

Each targeted person sees it as a card on their own Dashboard with
**Done**/**Snooze 3d** buttons. This page's **team completion overview**
always shows the real per-person status (Pending/Snoozed/Done) — snoozing
only hides a nudge from that person's own view, it never hides the true
status from you here.

## Scheduled maintenance tasks
Every morning at **8 AM**, the CRM automatically creates recurring maintenance
tasks for each active project and assigns them to the project lead (or project
owner if no lead is set) with an in-app bell notification — no email.

**As a manager, what you need to watch:**
- Open **Project Updates → any project** to see all tasks including auto-created
  ones. Overdue maintenance tasks show with the same red overdue flag as manual tasks.
- **Emptask** defaults to hiding these routine tasks (filter dropdown shows
  "Assigned tasks") so the list isn't dominated by hundreds of maintenance
  checks — switch to "Routine maintenance" or "All tasks" in the filter bar
  when you specifically need to audit them.
- Above the filter bar, a **Team workload** table shows each person's total
  tasks, status breakdown, and overdue count (assigned + routine combined) —
  click a name to see their full list.
- The **Employee Performance Report** (Reports on the dashboard) counts these
  tasks in each person's on-time completion %. If someone's % is dropping, check
  whether maintenance tasks are being dismissed without being marked Done.
- If a project isn't generating tasks, verify the project **status is Active**
  and a **service is set** on it. On-hold or completed projects are skipped.
- To trigger tasks for a missed date (e.g. after adding a new project
  mid-month), SSH into the server and run:
  ```
  php artisan app:dispatch-scheduled-tasks --date=YYYY-MM-DD
  ```

## Automated integrations
The CRM runs automated workflows with **Drishti** and **Social Media Dost**.
As a manager, what you need to know:

- **When a deal is Won**, the client is automatically provisioned in both tools
  — no manual re-entry. Check the client's Activity tab to confirm.
- **When SMDost brief is approved**, a draft invoice appears in the CRM for
  accounts to price. Watch for the notification if you oversee billing.
- **When SMDost content is sent to agency**, a *NEDS-led* content piece is
  auto-created in the CRM project (status: *Sent to partner*) with the copy
  already filled in. Open it and generate an upload link for the partner.
- **Client portal SSO** — clients with linked Drishti or SMDost accounts see
  one-click login buttons on their portal dashboard. If a client asks why the
  button is missing, check that their deal has been Won (which sets the external
  IDs).
- **Monthly briefs** are auto-created on the 1st of each month. If a client's
  brief didn't appear in SMDost, check that their project service is set to
  Social Media or GMB and that the SMDost Client ID is set on their CRM profile.

For full details and troubleshooting, see the
[Integrations guide](integrations.md).

## Bell notifications
As a manager, you receive bell notifications for:

- 🏆 **Deal won** — whenever any deal is marked Won, you and all admins are
  notified with the deal title, client name, and value.
- ⚠️ **Recurring invoice due in 7 days** — every morning at 8 AM, if any
  recurring-linked invoice is due in 7 days and hasn't been paid, you're alerted
  alongside the accounts team so you can follow up if needed.
- 🚩 **Payment promise broken** — the morning after a client's promised payment
  date passes with the invoice still unpaid, you're alerted alongside the
  accounts team. Fires once per promise, and again if a new promised date is
  set and also breaks.
- 📅 **Contract renewal due soon** — when an active recurring contract's end
  date is within 30 days, you and the accounts team (plus that client's sales
  rep) are notified once. Fires again if the contract is renewed to a later
  date and that new date later comes within the window too.
- **SMDost brief approved** — ✅ when a brief is approved in SMDost, a draft
  invoice appears in the CRM for accounts to price.
- 🌴 **Leave request submitted / reviewed** — whenever anyone requests leave,
  you and all other admins/managers are notified with their name and dates.
  You'll also see a banner on your dashboard when there are pending requests.

## Email alerts
**Morning digest (9 AM daily)** — your own personalised summary: overdue tasks,
tasks due today, call follow-ups, lead/deal follow-ups, and open tickets assigned
to you. If AI is enabled, it opens with a short AI-written line on what to
prioritise — the same line also shows as a banner on your dashboard for the
rest of the day.

**Project Updates Digest (9:15 AM daily, if AI is enabled)** — a leadership-only
summary of yesterday's AI-drafted client updates (drafted vs. approved &
sent), any drafts still awaiting review after 2+ days, and any active project
with no completed task or note in 5+ days. Only sent when there's something
to report. See the Admin guide's AI section for the full breakdown.

**Weekly owner digest (9 AM Monday, if AI is enabled)** — a leadership-only
paragraph synthesizing the week ahead: pipeline, MRR, cash expected, overdue
receivables, and how many clients Client Radar has flagged. Also shows as a
Monday dashboard banner. The paragraph itself is only sent when AI is on —
see the Admin guide's AI section for the full figure list. A "Weekly digest
history →" link on the dashboard opens the full week-by-week history with
trendlines, which is recorded every Monday regardless of whether AI is on.

**Stagnation alerts (10 AM daily)** — if any lead owned by a team member has had
no activity for 7 days, or a deal for 10 days, the owner is emailed automatically.
You don't need to chase people — the system does it.

## Tip
Check the **SLA at-risk** tickets and the **Overdue follow-ups** widget on the
dashboard regularly — both are leading indicators of service and sales health.
The stagnation alert emails give you a daily safety net for cold leads and deals.

**SLA breach emails:** when an open ticket passes its SLA deadline you receive a
one-time email showing how long overdue it is, the client, channel, priority,
and assignee, plus a direct link to the ticket. The email fires once per ticket —
no repeat hourly alerts. To see all currently breached tickets, go to **Tickets**
and tick the red **SLA breached** checkbox in the filter bar.
