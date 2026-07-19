# Admin — Recording Script

**Audience:** admins (watch `getting-started.md` first — includes 2FA
setup, which is required for this role).
**Target length:** ~10 minutes

**Before you record:** have the Users list, Services list, Menu Controller,
and Audit Log pages ready to click through. Log in as an admin user.

---

## Scene 1 — Intro (0:00–0:20)
**SAY:** "This one's for admins. You can do everything a manager can, plus
manage staff accounts, service lines, the sidebar, the audit log, and
backups. If you haven't watched the manager video, watch that one too —
everything there applies to you as well."

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

## Scene 7 — Client status and import (5:15–6:15)
**ON SCREEN:** Clients list, status filter dropdown, then Clients →
Import.
**SAY:** "Clients have three statuses — Prospect, in yellow, created when a
lead converts; Active, in green, set automatically the moment their deal
is marked Won; and Inactive, set manually when a relationship ends. The
list defaults to Active — use the filter to see the others. For bulk
import, Clients, Import, and download the template first — it's got
address, owner, and tags columns. Leave owner blank to assign to yourself."

## Scene 8 — Lead capture channels (6:15–7:00)
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

## Scene 9 — Audit log and backups (7:00–7:45)
**ON SCREEN:** Audit Log, filter by record type.
**SAY:** "The Audit Log shows who created, updated, or deleted any record,
and when — filter by type to investigate 'who changed this.' On backups,
there's genuinely nothing for you to do — the database backs itself up
every night automatically. If you ever need to restore one, that process is
written up separately for whoever's handling the server."

## Scene 10 — Integrations overview, brief (7:45–8:45)
**ON SCREEN:** A client's Activity tab.
**SAY:** "A few things run automatically behind the scenes that are worth
knowing about even though you won't touch them day to day: when a deal is
won, the client gets set up in our other tools automatically; the
website's contact form creates leads automatically; WhatsApp messages
create support tickets automatically. All of these leave a trace on the
client's Activity tab if you ever need to check whether something fired.
If any of these integrations misbehave, that's a job for whoever manages
the server — full troubleshooting steps live in the written admin and
integrations guides."

## Scene 11 — AI features and wrap-up (8:45–10:00)
**ON SCREEN:** A lead with a score badge, a ticket's Draft with AI button,
and the AI Usage Report.
**SAY:** "Last thing — the AI features, and there are a lot of them now.
Beyond lead scoring and Draft/Summarize buttons on leads and tickets,
there's a weekly owner digest every Monday, an AI narrative on the
Employee Performance report, Client Radar suggestions, a ticket triage
suggestion on new tickets, quotation line item suggestions, onboarding
task suggestions on a project, and a portal assistant clients can ask
questions to themselves. Every single one only ever drafts or suggests for
a human to review — Claude never sends, posts, or acts on its own. When
someone rates a draft helpful or not, that rolls up into the AI Usage
Report, which is the place to check what's actually earning its keep
versus what nobody's touching. If these buttons aren't showing up for
anyone, that's a server configuration question, not something to debug
from the UI. That's the admin essentials — the written admin guide has the
full detail on everything we skipped for time."
