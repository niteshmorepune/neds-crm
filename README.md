# NEDS CRM

A custom CRM for **Niranjan Enterprises Digital Solutions** (NEDS), a
Pune-based digital agency — leads through GST-compliant invoices, recurring
retainer billing, projects/tasks, a support desk, a client portal, and a set
of opt-in AI features powered by Anthropic's Claude.

Built on **Laravel 12** (PHP 8.2+) with **Livewire 3**, **Alpine.js**, and
**Tailwind CSS** — no separate SPA, deployed to plain PHP/MySQL shared
hosting on purpose. Live at
[crm.niranjanenterprises.co.in](https://crm.niranjanenterprises.co.in).

## Documentation

| Doc | Start here if you're... |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | ...touching this codebase at all — product spec, domain model, GST rules, deployment constraints, and the Decisions log. |
| [`docs/developer-guide.md`](docs/developer-guide.md) | ...a developer — local setup, architecture, conventions, testing, integrations. |
| [`docs/sitemap.html`](docs/sitemap.html) | ...trying to see every route in the app at a glance, mapped by access boundary. Open it directly in a browser. |
| [`docs/user-guides/`](docs/user-guides/) | ...internal NEDS staff — how to actually use the CRM (also served in-app under Help). |
| [`docs/deploy-checklist.md`](docs/deploy-checklist.md) | ...deploying a change to production. |

## Quick start

```bash
git clone https://github.com/niteshmorepune/neds-crm
cd neds-crm
composer setup   # install + .env + key:generate + migrate + npm install/build
composer dev     # runs server + queue listener + log tailing + Vite, all at once
```

See [`docs/developer-guide.md`](docs/developer-guide.md) for seeding demo
data, running tests, and everything else.

## License

Proprietary — internal software for Niranjan Enterprises Digital Solutions.
Not for redistribution.
