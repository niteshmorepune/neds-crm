# Automated deploy — GitHub Actions → Hostinger SSH

Same method as the Apex Brains app. On every push to **`master`**,
`.github/workflows/deploy.yml`:

1. installs PHP deps (`composer install --no-dev`),
2. builds the front-end (`npm ci && npm run build`) and **commits `public/build/`
   back to `master`** (Hostinger has no Node, so the compiled assets ride along
   in git — `public/build` is intentionally un-ignored),
3. SSHes into Hostinger and, in the app folder, runs an idempotent update:
   `git reset --hard origin/master` → `composer install --no-dev` →
   `migrate --force` → config/route/view/event cache → `queue:restart`.

The SSH step retries (Hostinger drops connections intermittently); the on-server
commands are idempotent, so re-runs are safe.

---

## 1. GitHub repository secrets
Settings → Secrets and variables → Actions → **New repository secret**. The
Hostinger account is the same one used for Apex Brains, so the first four values
are identical to that repo — copy them over (or reuse the same SSH key):

| Secret | Value |
|---|---|
| `HOSTINGER_SSH_HOST` | Hostinger SSH host/IP (hPanel → Advanced → SSH Access) |
| `HOSTINGER_SSH_PORT` | SSH port (Hostinger is usually **65002**) |
| `HOSTINGER_SSH_USERNAME` | SSH username (e.g. `u123456789`) |
| `HOSTINGER_SSH_PRIVATE_KEY` | Private key whose public half is in the server's `~/.ssh/authorized_keys` |
| `DEPLOY_PATH` | **New for this app** — absolute path to the neds-crm clone, e.g. `/home/u123456789/neds-crm` |

> If you reuse the existing Apex SSH key, no new key setup is needed — only
> `DEPLOY_PATH` is different.

## 2. One-time server bootstrap (SSH, run once)
```bash
# a) Create the subdomain in hPanel → Subdomains: crm.niranjanenterprises.co.in
#    Then set its Document Root to the clone's /public (step c).

# b) Clone the repo to the app folder (DEPLOY_PATH). Use a remote the server can
#    fetch non-interactively — HTTPS with a GitHub token, or a server SSH deploy
#    key with read access to the repo:
cd ~
git clone https://<token>@github.com/niteshmorepune/neds-crm.git neds-crm
cd neds-crm
git checkout master

# c) Point the subdomain's Document Root at this clone's public folder, in
#    hPanel → Subdomains → crm.niranjanenterprises.co.in → Document Root:
#       /home/USER/neds-crm/public
#    (No index.php path edits needed when the docroot IS the repo's /public.)

# d) App setup:
cp .env.production.example .env      # then edit real values
php artisan key:generate
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class=ServicesSeeder
php artisan db:seed --class=MenuItemsSeeder
php artisan db:seed --class=AdminUserSeeder   # login + change the password
php artisan config:cache && php artisan route:cache && php artisan view:cache

# e) Cron (hPanel → Cron Jobs):
#    * * * * * php /home/USER/neds-crm/artisan schedule:run >> /dev/null 2>&1
#    * * * * * php /home/USER/neds-crm/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

After this, every push to `master` auto-deploys. Trigger a manual run anytime
via the **workflow_dispatch** button (Actions → Deploy to Hostinger → Run).

## Notes
- The server git remote must `fetch` without prompts — bake a token into the
  HTTPS remote URL, or add a server-side SSH deploy key with repo read access.
- `.env` lives only on the server and is never overwritten by the deploy
  (it's gitignored; `git reset --hard` won't touch it).
- For the manual / first-principles steps and the production smoke check, see
  `docs/deploy-checklist.md`.
