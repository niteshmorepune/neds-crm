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

## Reports
From the **Reports** panel on the dashboard:

**Employee Performance Report** — per person, for a chosen month:
- tasks completed, **on-time %**, calls made, leads converted, **attendance %**,
  and daily reports submitted.
- Pick the month, then **Export CSV** for records or appraisals.

**Revenue Report** — for a chosen financial year:
- income **by month**, **by service**, and **by client**, split **recurring vs
  one-time**. Export CSV.

**Outstanding Receivables** — what clients still owe (shared with accounts).

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
- **Daily Reports → Team** shows what each person submitted.
- **Attendance** — use the dropdown on the Attendance page to switch between team
  members and view their monthly record. You can see all employees except admins.
- To correct an entry (e.g. someone forgot to check in), go to **Corrections**,
  pick the date, and update the **status** and **notes**. Times are set by
  the employee's own check-in/check-out and cannot be edited here — corrections
  are logged to the audit trail.

## Email alerts
**Morning digest (9 AM daily)** — your own personalised summary: overdue tasks,
tasks due today, call follow-ups, lead/deal follow-ups, and open tickets assigned
to you.

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
