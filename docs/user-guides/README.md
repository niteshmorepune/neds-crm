# NEDS CRM — User Guides

Help documentation for the Niranjan Enterprises Digital Solutions CRM at
**https://crm.niranjanenterprises.co.in**.

Start with **Getting Started**, then read the guide for your role.

| Guide | Who it's for |
|---|---|
| [Getting Started](getting-started.md) | Everyone — login, 2FA, dashboard, Resources, attendance, daily reports |
| [FAQ](faq.md) | Everyone — "why does it work this way" / "how do I..." questions pulled from real ones the team has asked |
| [Sales](sales.md) | Sales team — leads, pipeline, quotations, calls |
| [Support](support.md) | Support team — tickets, projects, tasks |
| [Accounts](accounts.md) | Accounts team — invoices, payments, recurring billing |
| [Manager](manager.md) | Managers — dashboards and reports |
| [Admin](admin.md) | Administrators — users, services, menu control, audit, backups |
| [Intern](intern.md) | Interns — clients (view), assigned projects and tasks, attendance, daily reports |
| [Telecaller](telecaller.md) | Telecallers — Lead Generation (shared queue) and Calling only |
| [Client Portal](client-portal.md) | Clients — the read-only customer portal |
| [Client FAQ](faq-client.md) | Clients — sanitized "how do I..." FAQ, live inside the portal itself (**FAQ** in the portal sidebar) — not a staff-shared doc |
| [Partner Portal](partner-portal.md) | Partners — the referral/content-collaborator portal |
| [Partner FAQ](faq-partner.md) | Partners — sanitized "how do I..." FAQ, live inside the portal itself (**FAQ** in the header) — not a staff-shared doc |
| [Integrations](integrations.md) | Managers & Admins — Drishti, SMDost, and the 10 automated workflows |
| [Troubleshooting](troubleshooting.md) | Admins & Managers — fixing common issues (biometric, SSO, integrations, email) |

In the app, the Help page only lists guides relevant to your role: everyone
gets Getting Started and FAQ, each team gets its own guide (Sales/Support/
Accounts/Intern/Telecaller), and Admin/Manager can see every guide —
including Client Portal, Partner Portal, Integrations, and Troubleshooting,
which no other role sees listed.

> Some AI helpers (lead scoring, "Draft with AI", "Summarize") only appear when
> an administrator has enabled AI for the workspace. If you don't see them,
> they're simply turned off — everything else works the same.

## Printable handouts (PDF)
Branded PDF versions for sharing with staff/clients live in
[`pdf/`](pdf/). To regenerate them after editing a guide (needs Node + a
Chromium-based browser such as Chrome or Edge installed):

```
npm run handouts
```

