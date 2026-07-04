# Admin — Recording Script

**Audience:** admins (watch `getting-started.md` first — includes 2FA
setup, which is required for this role).
**Target length:** ~8 minutes

**Before you record:** have the Users list, Services list, Menu Controller,
and Audit Log pages ready to click through. Log in as an admin user.

---

## Scene 1 — Intro (0:00–0:20)
**SAY:** "This one's for admins. You can do everything a manager can, plus
manage staff accounts, service lines, the sidebar, the audit log, and
backups. If you haven't watched the manager video, watch that one too —
everything there applies to you as well."

## Scene 2 — Adding and managing users (0:20–2:00)
**ON SCREEN:** Users → Add user → fill the form → Save. Then open an
existing user → point at the Active checkbox and the Biometric Device User
ID field.
**SAY:** "Public sign-up is off, so every staff account is created by an
admin. Users, Add user — name, email, role, and a temporary password,
leave Active ticked. Give them the login, they change the password
themselves. When someone leaves, don't delete them — edit them and untick
Active instead. That blocks their login but keeps their leads, deals, and
history intact. Also on this screen: the Biometric Device User ID field —
that's what links a person to their fingerprint machine punches, more on
that in a second. One safety net: you can't disable or delete your own
account, so you can't accidentally lock yourself out."

## Scene 3 — Biometric device mapping (2:00–2:45)
**ON SCREEN:** Edit a user, point at the Biometric Device User ID field.
**SAY:** "To map someone to the biometric machine: find their numeric ID
on the machine itself under Menu, User Management, then enter that exact
number here and save. From then on, their punches update their CRM
attendance automatically — but remind staff to still use the CRM check-in
button too, as covered in the getting-started video; the two work
together, they don't conflict."

## Scene 4 — Services (2:45–3:15)
**ON SCREEN:** Services list, click Add, then toggle one inactive.
**SAY:** "Services is your offering list — SEO, GMB, website dev, and so
on — these power every report's service breakdown. Add a new one, rename,
reorder, or toggle active. If a service is already in use somewhere, you
can't delete it — just deactivate it instead."

## Scene 5 — Menu Controller (3:15–4:15)
**ON SCREEN:** Menu Controller, point at the role grid, then a per-user
override.
**SAY:** "Menu Controller has two parts. The role grid controls real
access — this is the actual permission system. Per-user overrides just tidy
an individual's sidebar — cosmetic only, they don't grant or remove any
actual access, and there's a banner reminding you of that right on the
page. Changes apply the next time that person loads a page."

## Scene 6 — Client status and import (4:15–5:15)
**ON SCREEN:** Clients list, status filter dropdown, then Clients →
Import.
**SAY:** "Clients have three statuses — Prospect, in yellow, created when a
lead converts; Active, in green, set automatically the moment their deal
is marked Won; and Inactive, set manually when a relationship ends. The
list defaults to Active — use the filter to see the others. For bulk
import, Clients, Import, and download the template first — it's got
address, owner, and tags columns. Leave owner blank to assign to yourself."

## Scene 7 — Audit log and backups (5:15–6:00)
**ON SCREEN:** Audit Log, filter by record type.
**SAY:** "The Audit Log shows who created, updated, or deleted any record,
and when — filter by type to investigate 'who changed this.' On backups,
there's genuinely nothing for you to do — the database backs itself up
every night automatically. If you ever need to restore one, that process is
written up separately for whoever's handling the server."

## Scene 8 — Integrations overview, brief (6:00–7:00)
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

## Scene 9 — AI features and wrap-up (7:00–8:00)
**ON SCREEN:** A lead with a score badge, then a ticket's Draft with AI
button.
**SAY:** "Last thing — the AI features. When they're turned on, leads get
an automatic 0 to 100 score with a reason, and there are Draft and
Summarize buttons on leads and tickets. Claude only ever suggests text for
a staff member to edit and send themselves — it never sends or acts on its
own. If these buttons aren't showing up for anyone, that's a server
configuration question, not something to debug from the UI. That's the
admin essentials — the written admin guide has the full detail on
everything we skipped for time."
