# Admin — Recording Script

**Audience:** admins (watch `getting-started.md` first — includes 2FA
setup, which is required for this role).
**Target length:** ~13 minutes

**Before you record:** have the Users list, Services list, Menu Controller,
Subscriptions, and Audit Log pages ready to click through, plus (if
`GOOGLE_MEET_ENABLED`) the Profile page's Google Account section. Log in as
an admin user.

---

## Scene 1 — Intro (0:00–0:20)
**SAY:** "This one's for admins. You can do everything a manager can, plus
manage staff accounts, service lines, the sidebar, subscriptions, the audit
log, and backups. If you haven't watched the manager video, watch that one
too — everything there applies to you as well, including Notice Board,
Team Nudges, Sales Incentives, and Staff Productivity Ranking, which are
covered there in full."

## Scene 2 — Adding and managing users, and additional roles (0:20–2:15)
**ON SCREEN:** Users → Add user → fill the form → Save. Then open an
existing user → point at the Active checkbox, the Biometric Device User ID
field, and the Additional roles checkboxes further down the form.
**SAY:** "Public sign-up is off, so every staff account is created by an
admin. Users, Add user — name, email, role, and a temporary password,
leave Active ticked. Give them the login, they change the password
themselves. When someone leaves, don't delete them — edit them and untick
Active instead. That blocks their login but keeps their leads, deals, and
history intact. One thing worth knowing: everyone has a primary role, the
dropdown, but you can also tick Additional roles further down — say,
someone doing both Sales and Support. That expands what they can directly
do, who they show up for in notifications, and their sidebar — but it
never changes which dashboard panel they see, that always follows the
primary role only. Also on this screen: the Biometric Device User ID field —
that's what links a person to their fingerprint machine punches, more on
that in a second. One safety net: you can't disable or delete your own
account, so you can't accidentally lock yourself out."

## Scene 3 — Biometric device mapping (2:15–3:00)
**ON SCREEN:** Edit a user, point at the Biometric Device User ID field.
**SAY:** "To map someone to the biometric machine: find their numeric ID
on the machine itself under Menu, User Management, then enter that exact
number here and save. From then on, their punches update their CRM
attendance automatically — but remind staff to still use the CRM check-in
button too, as covered in the getting-started video; the two work
together, they don't conflict."

## Scene 4 — Services and Festivals (3:00–3:45)
**ON SCREEN:** Services list, click Add, then toggle one inactive. Then
Sidebar → Festivals, point at an upcoming entry.
**SAY:** "Services is your offering list — SEO, GMB, website dev, and so
on — these power every report's service breakdown. Add a new one, rename,
reorder, or toggle active. If a service is already in use somewhere, you
can't delete it — just deactivate it instead. Festivals is a separate
calendar you manage — it drives a dashboard countdown banner for everyone,
and for clients on Social Media or GMB, an AI-drafted greeting caption 7
days ahead of each date. Fixed national holidays are pre-loaded
permanently; lunar and regional festivals shift every year, so re-check
and re-add those every January."

## Scene 5 — Client Radar, brief (3:45–4:15)
**ON SCREEN:** Sidebar → Client Radar.
**SAY:** "Client Radar is admin and manager only — it flags active clients
worth a proactive check-in based on live signals: no contact in 14 days,
declining activity, an overdue invoice, or a growth opportunity where
they're only on one service. It's covered in full in the manager video,
worth a watch since you share this page."

## Scene 6 — Menu Controller (4:15–5:15)
**ON SCREEN:** Menu Controller, point at the role grid, then a per-user
override.
**SAY:** "Menu Controller has two parts. The role grid controls real
access — this is the actual permission system. Per-user overrides just tidy
an individual's sidebar — cosmetic only, they don't grant or remove any
actual access, and there's a banner reminding you of that right on the
page. Changes apply the next time that person loads a page."

## Scene 7 — Subscriptions (5:15–6:00)
**ON SCREEN:** Sidebar → Subscriptions → click Add → fill name, vendor,
cost per cycle, billing cycle, next renewal date, reminder window → Save.
**SAY:** "Subscriptions is admin-only — not even managers see this one,
since it's internal vendor and billing info. Track the tools NEDS itself
pays for: the Claude subscription, Hostinger hosting, domain renewals,
anything else, so renewals stop being tribal knowledge. Each entry has a
name, vendor, cost per cycle, a billing cycle — monthly, quarterly, or
yearly — a next renewal date, and how many days before to remind you. Once
a reminder window opens, every active admin gets both an email and a bell
notification, so you won't miss it even on a day you're not logged in. And
once a renewal date passes, it's treated as auto-renewed — the date just
rolls forward a cycle on its own. If you actually cancel something instead
of renewing, untick Active on that entry rather than deleting it."

## Scene 8 — Client status and import (6:00–7:00)
**ON SCREEN:** Clients list, status filter dropdown, then Clients →
Import.
**SAY:** "Clients have three statuses — Prospect, in yellow, created when a
lead converts; Active, in green, set automatically the moment their deal
is marked Won; and Inactive, set manually when a relationship ends. The
list defaults to Active — use the filter to see the others. For bulk
import, Clients, Import, and download the template first — it's got
address, owner, and tags columns. Leave owner blank to assign to yourself."

## Scene 9 — Lead capture channels (7:00–7:45)
**ON SCREEN:** A lead in Lead Generation with source "Meta Ads", then
mention the website form and WhatsApp.
**SAY:** "Leads flow in automatically from three channels now — no manual
entry needed for any of them. The website contact form creates a lead on
every submission. A WhatsApp message from an unknown number creates one
too. And Meta Lead Ads — a Facebook or Instagram lead form submission —
comes in via webhook and shows up here with source Meta Ads. All three
auto-assign to whichever Sales rep has the fewest open leads right now, so
nothing sits unowned. If Meta Lead Ads ever needs re-configuring, the
step-by-step is in the written admin guide."

## Scene 10 — Audit log and backups (7:45–8:30)
**ON SCREEN:** Audit Log, filter by record type.
**SAY:** "The Audit Log shows who created, updated, or deleted any record,
and when — filter by type to investigate 'who changed this.' On backups,
there's genuinely nothing for you to do — the database backs itself up
every night automatically. If you ever need to restore one, that process is
written up separately for whoever's handling the server."

## Scene 11 — Integrations overview, brief (8:30–9:30)
**ON SCREEN:** A client's Activity tab.
**SAY:** "A few things run automatically behind the scenes that are worth
knowing about even though you won't touch them day to day: when a deal is
won, the client gets set up in our other tools automatically; the
website's contact form creates leads automatically; WhatsApp messages
create support tickets automatically; and — covered properly in a moment —
Google Meet calls can be scheduled and imported onto a client's Calls tab.
All of these leave a trace on the client's Activity tab if you ever need
to check whether something fired. If any of these integrations misbehave,
that's a job for whoever manages the server — full troubleshooting steps
live in the written admin and integrations guides."

## Scene 12 — Connecting Google for Create Meeting (9:30–10:15)
**ON SCREEN:** Profile → Google Account section → Connect Google Account →
(after real consent) point at "Connected as…" and the Disconnect button.
Then briefly show Create Meeting on a client's Calls tab.
**SAY:** "One more setup step, if we're using Google Meet — and this one's
yours alone, nobody else sees this section. On your Profile page, Google
Account, click Connect Google Account and sign in with NEDS's own Google
account, not your personal one. Once that's done, every staff member —
Sales, Support, everyone — gets a Create Meeting button on any client or
lead's Calls tab: it schedules a real Meet call through this same
connection, emails the client the invite automatically, and shows them the
link to share directly too. Nobody else ever needs to connect their own
Google account, and if you ever need to swap which Google account this
uses, just Disconnect and Connect again."

## Scene 13 — AI features and wrap-up (10:15–12:45)
**ON SCREEN:** A lead with a score badge, a ticket's Draft with AI button,
the Employee Performance Report's ranked table, and the AI Usage Report.
**SAY:** "Last thing — the AI features, and there are a lot of them now.
Beyond lead scoring and Draft/Summarize buttons on leads and tickets,
there's a weekly owner digest every Monday, an AI narrative and a
per-role productivity ranking with suggested focus areas on the Employee
Performance report, Client Radar suggestions, a ticket triage suggestion
on new tickets, quotation line item and scope-of-work suggestions,
onboarding task suggestions on a project, Google Meet transcript
summaries, and a portal assistant clients can ask questions to themselves.
Every single one only ever drafts or suggests for a human to review —
Claude never sends, posts, or acts on its own. When someone rates a draft
helpful or not, that rolls up into the AI Usage Report, which is the place
to check what's actually earning its keep versus what nobody's touching.
If these buttons aren't showing up for anyone, that's a server
configuration question, not something to debug from the UI. That's the
admin essentials — the written admin guide has the full detail on
everything we skipped for time, and the manager video covers Notice Board,
Team Nudges, and Sales Incentives, which you share with that role."
