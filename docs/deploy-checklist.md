# Deployment checklist — Hostinger Business (shared hosting)

> **Automated deploys** run via GitHub Actions on every push to `master`
> (`.github/workflows/deploy.yml`) — same method as the Apex Brains app. See
> **`docs/deploy-github-actions.md`** for the required secrets and the one-time
> server bootstrap. This checklist is the manual / first-principles reference
> and the post-deploy smoke check.

Target: PHP 8.2+, MySQL only. No Node, Redis, Docker, or long-running daemons.
Queue/cache/session = database; scheduler + queue run from cron.

**Production domain:** `crm.niranjanenterprises.co.in` (Hostinger subdomain;
`crm.talktonitesh.com` is kept resolving to the same app during transition).
Create it in hPanel → Subdomains, then read the **Document Root** it assigns —
for a subdomain that is NOT the account's main `public_html`. It's typically
something like `~/domains/talktonitesh.com/public_html/crm` (or a dedicated
folder shown in hPanel). Wherever this checklist says `public_html`, use that
subdomain document root instead.

---

## 0. Before you start (local)
- [ ] `php artisan test` is green on `master`.
- [ ] `npm run build` has been run and `public/build/` is up to date.
      **`public/build/` is gitignored**, so it will NOT arrive via a Git deploy —
      see step 3 for how to ship the compiled assets.
- [ ] You have the production values ready (see `.env.production.example`).

## 1. Database (hPanel)
- [ ] Create a MySQL database + user; note host, name, user, password.
- [ ] Grant the user all privileges on that database.

## 2. Upload the application
Document root on Hostinger is `public_html`. Lay the app out so the framework
lives **above** the web root:

```
/home/USER/
├── neds-crm/            ← entire repo EXCEPT the contents of /public
│   ├── app/  bootstrap/  config/  database/  routes/  storage/  vendor/  …
│   └── .env             ← created here on the server (step 4)
└── public_html/         ← contents of the repo's /public folder
    ├── index.php        ← edit paths (step 2b)
    ├── build/           ← compiled assets (step 3)
    └── .htaccess  robots.txt  favicon.ico  …
```

- [ ] **2a.** Upload the repo to `~/neds-crm` (SSH `git clone`, hPanel Git, or SFTP).
- [ ] **2b.** Copy the **contents** of `~/neds-crm/public/` into `public_html/`,
      then edit `public_html/index.php` so the two require paths point one level up:
      ```php
      require __DIR__.'/../neds-crm/vendor/autoload.php';
      $app = require_once __DIR__.'/../neds-crm/bootstrap/app.php';
      ```

## 3. Compiled front-end assets (no Node on the server)
Pick ONE:
- [ ] **FTP/SFTP:** upload your locally-built `public/build/` into `public_html/build/`.
      Re-upload whenever assets change.
- [ ] **Git deploy:** since `public/build/` is gitignored, either (a) commit a
      built copy on a deploy branch, or (b) keep using the FTP option above.
      Do **not** rely on a plain Git deploy to carry assets.

Verify after go-live: the dashboard loads styled (Tailwind) and the Services
Overview donut renders (Chart.js is loaded from CDN, so it needs outbound HTTPS).

## 4. Environment
- [ ] Create `~/neds-crm/.env` from `.env.production.example` and fill real values.
- [ ] `php artisan key:generate` (sets `APP_KEY`).
- [ ] Confirm: `APP_ENV=production`, `APP_DEBUG=false`,
      `APP_URL=https://crm.niranjanenterprises.co.in`, `SESSION_SECURE_COOKIE=true`,
      `SESSION_LIFETIME=480`, `ENFORCE_TWO_FACTOR_ENROLLMENT=true`.
- [ ] If the DB password contains `#`, wrap it in quotes: `DB_PASSWORD="…#…"`.

## 5. Install & migrate (SSH)
- [ ] `composer install --no-dev --optimize-autoloader`
      (all dev/test tools — Breeze, Pest, Pint, Faker — are in `require-dev`, so
      this is safe.) If SSH/Composer isn't available, run it locally with the
      matching PHP version and upload `vendor/`.
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`  (symlinks `public_html/storage` → `storage/app/public`)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`

## 6. Cron (hPanel → Advanced → Cron Jobs)
- [ ] Scheduler (every minute):
      ```
      * * * * * php /home/USER/neds-crm/artisan schedule:run >> /dev/null 2>&1
      ```
- [ ] Queue drain (every minute — DB queue, no daemon):
      ```
      * * * * * php /home/USER/neds-crm/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
      ```
  The scheduler covers reminders, recurring invoices, overdue flags, SLA checks,
  daily-report reminders, and the **02:00 nightly DB backup**.

## 7. SSL & domain
- [ ] Enable SSL for the domain (hPanel) and confirm `https://` loads.
- [ ] `APP_URL` uses `https://` (the app also force-upgrades URLs in production).

## 8. First-run data
- [ ] `php artisan db:seed --class=ServicesSeeder`  (NEDS service lines)
- [ ] `php artisan db:seed --class=MenuItemsSeeder`  (sidebar)
- [ ] Create the admin user: `php artisan db:seed --class=AdminUserSeeder`
      (idempotent/production-safe). Seeds `niranjan.enterprisespune@gmail.com`
      with password `password` — **log in and change it immediately**.
- [ ] Do **NOT** run `DemoDataSeeder` in production (it refuses anyway).
- [ ] Import the customer CSV via Clients → Import.

## 9. Post-deploy smoke check
- [ ] Log in as admin → because `ENFORCE_TWO_FACTOR_ENROLLMENT=true`, you're sent
      to the profile to set up 2FA. Scan the QR, confirm, save the recovery codes.
- [ ] Dashboard renders styled with the donut + stat cards.
- [ ] Create a test client, quotation → invoice; download the invoice PDF.
- [ ] Trigger one email path (e.g. a ticket reply) and confirm delivery.
- [ ] Confirm the backup ran: check `storage/app/backups/` after 02:00, or run
      `php artisan app:backup-database` once by hand.

## 10. On every later deploy
- [ ] Upload changed code (+ `public/build/` if assets changed).
- [ ] `composer install --no-dev --optimize-autoloader` (if deps changed).
- [ ] `php artisan migrate --force`
- [ ] `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`

---

### Notes
- **Migrations are append-only** — never edit a merged migration.
- **Backups** cover the database only; see `docs/backup-restore.md` for the
  restore procedure and pulling copies off-server.
- **AI features** stay off until `AI_ENABLED=true` + a real `ANTHROPIC_API_KEY`
  are set; failures degrade gracefully and never block core workflows.
