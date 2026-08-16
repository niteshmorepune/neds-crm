# Getting Started (everyone)

Welcome to the NEDS CRM. This covers the basics every team member needs.

## 1. Logging in
When your account is first created, you'll get a **"Set my password"** email —
click the link and choose your own password before you log in for the first
time (the link expires after an hour; if it's expired, use **Forgot your
password?** on the login page to get a fresh one).

1. Go to **https://crm.niranjanenterprises.co.in**.
2. Enter your **email** and the **password** you set.
3. Click **Log in**.

If you ever forget your password afterwards, click **Forgot your password?**
on the login page, or ask an admin to reset it for you from the Users screen.

## 2. First login — set up Two-Factor Authentication (admins & managers)
For security, **admins and managers must set up an authenticator app** the first
time they log in. (Sales, support and accounts users skip this.)

1. After logging in you'll land on the **2FA setup** screen.
2. Open an authenticator app on your phone — **Google Authenticator**, **Authy**,
   or **1Password** all work.
3. **Scan the QR code** (or type the key shown).
4. Enter the **6-digit code** the app shows and click **Confirm**.
5. **Save your recovery codes** somewhere safe — each can be used once if you
   lose your phone.

From then on, you'll enter a 6-digit code each time you log in.

## 3. Change your password
Click your **name** (top-right) → **Profile** → **Update Password**. Set a
password only you know.

**If you own or lead any client projects:** on the same **Profile** page,
paste your Google Calendar appointment-scheduling link into the **Google Meet
scheduling link** field and save. This is what powers the **Schedule a
Meeting** button clients see on their portal project page — without it set,
that button simply won't appear for your clients.

**Meet notes and Create Meeting (optional, separate from the scheduling link
above):** if enabled, an admin connects NEDS's Google account once (Profile
→ Google Account — admin-only screen, not something everyone needs or sees).
Once that's done, **everyone** gets a **Create Meeting** button on any
client or lead's Calls tab — it schedules a real Google Meet call through
that same connection, emails the client the invite automatically, and shows
you the link to share directly too. There's also **Import Meet Notes**, for
a call that already happened outside that flow — it pulls in the recording/
transcript Google Meet already generated. If AI is also enabled, Claude can
summarize an imported transcript into short "Key points / Decisions / Action
items" notes — either automatically after import or via a **Summarize with
AI** button.

## 4. Finding your way around
- **Left sidebar** — your modules. What you see depends on your role.
- **Search bar** (top) — find any client, lead, deal, invoice, ticket or project
  by name or number.
- **☎ Log a call** (top) — quickly record a phone call with a client or lead.
- **🔔 Bell icon** (top) — your notification centre. Alerts vary by role but
  include: tasks assigned to you, call follow-up reminders, new leads, hot
  leads (AI score ≥ 70), deal won, payment recorded, and upcoming recurring
  invoice due dates. Click a notification to go straight to the record; click
  **Dismiss** to clear it. If the deal or invoice a notification points to
  has since been deleted, it shows as plain text with **"(deal deleted)"**
  or **"(invoice deleted)"** instead of a link — the alert was accurate when
  it fired, the record just no longer exists.
- **Your name** (top-right) — Profile and Log out.

## 4a. Overdue tasks alert
If you have any tasks past their due date, a **red banner** appears at the top of
your dashboard when you log in. Click **View tasks →** to see them and clear the
backlog.

## 4b. My Day
**Sidebar → My Day** is a single worklist pulling together everything due or
overdue that's yours: tasks, lead/deal follow-ups, call follow-ups, and (for
Support) SLA-breached tickets. Instead of checking five separate screens each
morning, check this one page first — each item links straight to the record so
you can act on it, and the list disappears once you're caught up.

## 4c. Notice Board
If admin or a manager has posted a company-wide notice — office closures,
holiday reminders, policy changes — it shows as a 📣 banner at the top of
your **Dashboard**, above the overdue-tasks alert. Click the **×** to dismiss
it; dismissing is remembered only on that browser/device, so it may reappear
if you check the CRM from a different computer or phone. A notice
disappears on its own once its end date passes.

## 4d. Reminders (Team Nudges)
Below the Notice Board, a **Reminders** card on your Dashboard shows any
nudges admin/manager has targeted at your role (or everyone) — things like
"log every active client as a Deal" or "route issues through Tickets."
- Click **Done** once you've actually done it.
- Click **Snooze 3d** to hide it from your view for 3 days — snoozing only
  hides it from *you*; admin/manager still see the real status on their side,
  so snoozing isn't a way to make something go away for good.
- Some reminders clear themselves automatically the moment you do the real
  thing they're asking for (e.g. logging a Ticket) — no click needed for those.

## 4e. Follow-up Reminders
Also on your **Dashboard**, below Reminders (Team Nudges), a **Follow-up
Reminder** widget lets you set a personal reminder against any client — a
call to make, a report to share, a quotation to send, a deal to chase.
Click **+ Add follow-up reminder**, pick the client (optional — you can also
leave it blank for a general reminder), set the date/time and a one-line
next action, and save. You'll get a bell notification once it's due, and it
stays listed on your Dashboard (soonest first) until you click **Done**.
This is separate from a Call Log's own follow-up reminder (see the Sales/
Support guides) — that one only exists once you've actually logged a call;
this one is for setting a reminder ahead of time, for anything.

**Sending a quotation creates one of these automatically** — a 3-day
reminder to follow up, no need to set it yourself. See Sales guide →
Quotations.

## 4f. Meeting Invitations
If a colleague schedules a **Google Meet** call (via **Create Meeting** on a
client or lead's Calls tab — see the Sales/Support guides) and adds you as
an internal attendee, it shows up as a card on your own **Dashboard** with
the meeting title, client, time, and organiser, plus **Accept**/**Decline**
buttons and a direct **Join Google Meet** link. This is separate from the
real Google Calendar invite the client receives — it's just a CRM-side
heads-up and RSVP so you don't have to check email to see it, and it only
shows upcoming meetings, soonest first.

## 4g. Resources
**Sidebar → Resources** has two tabs:
- **Files** — a shared internal file library (plugin builds, certificates,
  templates…). Only Admin/Manager can upload, edit, or delete a file (see the
  Admin guide for how); everyone else sees a read-only list.
- **Links** — a company-wide list of official links (hosting panel, domain
  registrar, etc.), grouped and filterable by department/purpose.

Both can be restricted to specific roles when added — a Support-only file or
an Accounts-only link simply won't appear for anyone outside that role. Leave
"Visible to" blank to show it to everyone. Per-client links still live on
each client's own page, unaffected by this page.

Every **client's own page** also has a **Links** tab — that client's
website, Google Business Profile, socials, Drive folder, payment links, or
anything else useful for serving them. Anyone who can open the client's
page can see these; who can add/edit them follows the same rule as
everything else on that client's page.

## 5. Attendance — check in and out
On the **Dashboard**, use the attendance widget:
- **Check in** when you start your day.
- **Check out** when you finish.

**Also do your biometric fingerprint punch as usual** — do both, not one or
the other. The two work together automatically: your CRM check-in/out marks
you present immediately, and your biometric punch fills in or corrects the
exact time within a few minutes once it syncs. Whichever is the **earliest**
check-in and **latest** check-out (from either source) is what's kept — so
there's no conflict and nothing to undo if both are recorded.

Your manager can see attendance, and it feeds the performance reports.

## 5a. Requesting leave
Open **Leave Requests** in the sidebar to apply: pick a **leave type** (Full
Day or Half Day), a start date, end date, and a short reason, then submit. A
Half Day request must be for a single date — start and end date must match.
Any admin or manager can approve or reject it — you'll get a bell
notification either way, and once decided, your own Leave Requests list
shows who reviewed it. Once approved, your attendance for those days is
automatically marked **Leave** (or **Half Day** for a half-day request) —
Sundays are skipped since they're not office days. You can cancel a request
yourself as long as it's still pending.

## 6. Daily report — end of day
At the end of the day, open **Daily Reports** and submit a short "what I did
today". Some numbers (tasks completed, calls made) are filled in automatically;
add a sentence or two on your day and submit. You'll get a reminder at 6 PM.

**My Tasks** on this page is grouped by project, with the project needing the
soonest attention shown first. Within each project, tasks someone assigned you
directly appear up top; **🔄 routine maintenance** tasks — things like
"Google Search Console review" or "SSL certificate expiry check" that the CRM
creates automatically on a schedule for every active project (no one assigned
these to you personally) — are collapsed under a "click to view" line so they
don't bury the tasks that actually need your judgement. Click that line
whenever you want to see or update them.

## 6a. Best Employee of the Quarter
**Sidebar → Best Employee** shows your own recognition history. Each
financial-year quarter, AI reviews performance numbers already tracked
elsewhere in the CRM (tasks, calls, attendance, and more) and suggests a
top performer per department, plus one company-wide winner. If Admin/Manager
approve a suggestion naming you, you'll get a bell notification and a
downloadable certificate — recognition only, not tied to any payout.

## 7. Morning digest email
Every morning at **9 AM** you'll receive a personalised email summarising your
day — overdue tasks, tasks due today, call follow-ups, and any open tickets
assigned to you. No email means you have a clean plate for the day.

If anything is due, the email opens with a short **AI-written summary** of
what to prioritize — the same text also appears as a banner on your dashboard
for the rest of the day. It's generated only from your own tasks/follow-ups,
never invents anything, and disappears automatically the next day.

## 8. Logging out

Click your **name** (top-right) → **Log Out**. Always log out on shared devices.

---
Next: read the guide for your role — [Sales](sales.md), [Support](support.md),
[Accounts](accounts.md), [Manager](manager.md), [Admin](admin.md), or
[Intern](intern.md).
