# Manager guide

Managers see the **full company dashboard** and the **management reports**, plus
every operational module for oversight.

> Managers are required to use **Two-Factor Authentication** — see
> [Getting Started](getting-started.md) if you haven't set it up.

## The company dashboard
Your dashboard shows the whole business at a glance:
- **Stat cards** — Total / Active / Inactive **Clients** and **Tasks Overview**,
  each with the % change vs last month.
- **Services Overview** — a donut of projects by service line (SEO, GMB, Website,
  Social, Ads…).
- **Task Summary** — Assigned / Pending / Overdue / Completed.
- **Link panels** — quick access to Daily Reports, the Project Dashboard, and the
  Reports below.

## Client Radar
A **Client Radar** sidebar item (and a dashboard banner when clients are
flagged) surfaces active clients worth a proactive check-in:
- **No Contact** — no note, call, or ticket in the last 14 days.
- **Declining Activity** — recent touches well below the prior 30 days.
- **Overdue Invoice** — at least one overdue invoice.
- **Growth Opportunity** — only using one service line even though more are
  active — a natural upsell prompt.

Everything is computed live from existing data, nothing is stored. Click
**✨ Suggest action** next to a client to have Claude draft a specific next
step from that client's flags — generated on demand, per client, so it's
never run automatically as a batch.

## Reports
From the **Reports** panel on the dashboard:

**Employee Performance Report** — per person, for a chosen month:
- tasks completed, **on-time %**, calls made, leads converted, **attendance %**,
  and daily reports submitted.
- Pick the month, then **Export CSV** for records or appraisals.
- Click **✨ Generate AI Summary** for a narrative read of the table — trends,
  standouts, and anyone whose numbers suggest they may need support. It's
  generated only from the numbers already on the page (never invents a
  reason behind them) and is **visible to Admin/Manager only** — it's never
  shown to the employee it's about, so use it as a starting point for a
  conversation, not as a rating you show them.

**Revenue Report** — for a chosen financial year:
- income **by month**, **by service**, and **by client**, split **recurring vs
  one-time**. Export CSV.

**Outstanding Receivables** — what clients still owe (shared with accounts).

## Bell notifications
As a manager, you receive bell notifications for:

- 🏆 **Deal won** — whenever any deal is marked Won, you and all admins are
  notified with the deal title, client name, and value.
- ⚠️ **Recurring invoice due in 7 days** — every morning at 8 AM, if any
  recurring-linked invoice is due in 7 days and hasn't been paid, you're alerted
  alongside the accounts team so you can follow up if needed.
- **SMDost brief approved** — ✅ when a brief is approved in SMDost, a draft
  invoice appears in the CRM for accounts to price.
- 🌴 **Leave request submitted / reviewed** — whenever anyone requests leave,
  you and all other admins/managers are notified with their name and dates.
  You'll also see a banner on your dashboard when there are pending requests.

## Oversight
You have access to Leads, Deals, Quotations, Invoices, Projects, Tasks, Tickets,
Clients, Attendance, Calls and Daily Reports so you can monitor and step in
anywhere. Use the **search bar** to jump to any record.

**Seeing who services which client:** open any client's profile and click the
**Services** tab. The **Projects** table now has a **Team** column showing the
Lead (primary owner) and any additional team members for each project/service.
This is the fastest way to answer "which of our team handles GMB for ABC Corp?"

**Project Updates → My Services:** the Project Updates page has a new **My Services**
toggle that filters the list to only projects where you are the Lead or a team member.
Useful for managers who also run their own service accounts.

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
- **Leave Requests → Review pending** — any admin or manager can approve or
  reject a leave request (you can't approve your own). Approving a request
  automatically marks the employee's attendance as **Leave** for each office
  day in the range (Sundays are skipped); rejecting lets you add a short note
  explaining why.

## Email alerts
**Morning digest (9 AM daily)** — your own personalised summary: overdue tasks,
tasks due today, call follow-ups, lead/deal follow-ups, and open tickets assigned
to you. If AI is enabled, it opens with a short AI-written line on what to
prioritise — the same line also shows as a banner on your dashboard for the
rest of the day.

**Stagnation alerts (10 AM daily)** — if any lead owned by a team member has had
no activity for 7 days, or a deal for 10 days, the owner is emailed automatically.
You don't need to chase people — the system does it.

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

## Tip
Check the **SLA at-risk** tickets and the **Overdue follow-ups** widget on the
dashboard regularly — both are leading indicators of service and sales health.
The stagnation alert emails give you a daily safety net for cold leads and deals.

**SLA breach emails:** when an open ticket passes its SLA deadline you receive a
one-time email showing how long overdue it is, the client, channel, priority,
and assignee, plus a direct link to the ticket. The email fires once per ticket —
no repeat hourly alerts. To see all currently breached tickets, go to **Tickets**
and tick the red **SLA breached** checkbox in the filter bar.
