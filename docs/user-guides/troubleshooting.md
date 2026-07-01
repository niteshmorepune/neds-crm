# CRM Troubleshooting Guide

For admins and managers. Each section follows the same pattern:
**Symptom → What to check → Fix.**

All server commands are run over SSH:
`ssh -i ~/.ssh/hostinger_deploy -p 65002 u314035009@89.117.188.107`

---

## 1. Biometric attendance not syncing

**Symptom:** Staff punch the machine but CRM attendance is not updated (no
check-in / check-out time appears, or only the manual dashboard entry shows).

**Check 1 — Is the device serial set in the server env?**
```
cd /home/u314035009/neds-crm && grep BIOMETRIC_DEVICE_SERIAL .env
```
Should show `BIOMETRIC_DEVICE_SERIAL=NFZ8243301103`. If blank:
```
echo 'BIOMETRIC_DEVICE_SERIAL=NFZ8243301103' >> .env && php artisan config:cache
```

**Check 2 — Is the device pointing at the CRM?**
On the machine: Menu → Cloud Server → ADMS. Verify:
- Server Address: `crm.talktonitesh.com`
- Port: (blank or 443)
- HTTPS: ON

**Check 3 — Is the staff member's Device User ID mapped?**
In the CRM: Admin → Users → Edit the person. The **Biometric Device User ID**
field must contain their numeric ID from the machine's Device Users list
(Menu → User Mgt on the machine).

**Check 4 — Is the endpoint reachable at the exact path the device uses?**
```
curl -s "https://crm.talktonitesh.com/iclock/cdata?SN=NFZ8243301103"
```
Should return `GET OPTION FROM:NFZ8243301103`. If it doesn't, the CRM is down
or config:cache wasn't run after setting the env var.

**Important:** the URL has **no `/api` prefix**. The ADMS protocol used by
eSSL/ZKTeco biometric devices always POSTs to `/iclock/cdata` — the device's
Cloud Server settings only let you set the server address and port, not a
path. Testing against `/api/iclock/cdata` will falsely appear to work (it
used to be registered there) while the real device 404s silently — this is
exactly what caused a real outage on 2026-07-01 where punches never synced
and nothing appeared in the logs (404s aren't logged by Laravel by default).
If a future change ever moves this route back under `/api`, punches will
silently stop syncing again with no log trace — always verify with the
no-prefix URL above, not the `/api/...` one.

---

## 2. AI features (lead scoring, Draft with AI, Summarize) not appearing

**Symptom:** The ✨ buttons and score badges are missing. Leads show no score.

**Check — Are the AI env vars set?**
```
cd /home/u314035009/neds-crm && grep -E 'AI_ENABLED|ANTHROPIC' .env
```
Should show:
```
AI_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-haiku-4-5-20251001
```

If any are missing or `AI_ENABLED=false`:
```
# Edit .env to add/fix the values, then:
php artisan config:cache
```

**Important:** The model must be `claude-haiku-4-5-20251001`. Any other model ID
(including `claude-sonnet-4-20250514`) returns 404 from Anthropic and all AI
calls fail silently.

---

## 3. Morning digest / daily reminders / stagnation alerts not sending

**Symptom:** Staff report they're not receiving the 9 AM morning digest, 6 PM
daily report reminder, or stagnation alert emails.

**Check 1 — Is it Sunday?**
All four scheduled email commands skip Sundays automatically. No action needed.

**Check 2 — Does the staff member have anything due?**
The morning digest only sends if there is something to report (overdue tasks,
tasks due today, follow-ups, open tickets). An empty digest is skipped. A clean
slate means no email — that's correct behaviour.

**Check 3 — Is the cron running?**
```
cd /home/u314035009/neds-crm && php artisan schedule:list
```
All scheduled jobs should appear. If the list is empty or the command errors,
check that the Hostinger cron entry is still active (hPanel → Cron Jobs →
`/opt/alt/php83/usr/bin/php /home/u314035009/neds-crm/artisan schedule:run`
every minute).

**Check 4 — Is SMTP working?**
```
cd /home/u314035009/neds-crm && php artisan tinker --no-interaction
```
Then paste:
```php
Mail::raw('Test', fn($m) => $m->to('niranjan.enterprisespune@gmail.com')->subject('CRM SMTP test'));
```
If it throws, check `MAIL_*` settings in `.env` and run `php artisan config:cache`.

---

## 4. WhatsApp tickets not being created automatically

**Symptom:** A client messages on WhatsApp but no ticket appears in the CRM.

**Check 1 — Is wadesk.in running?**
Visit https://wadesk.in. If it's down, SSH to the VPS:
```
ssh root@72.60.98.246
docker compose -f /path/to/wadesk/docker-compose.yml ps
docker compose -f /path/to/wadesk/docker-compose.yml up -d
```

**Check 2 — Webhook token mismatch**
wadesk.in sends `Authorization: Bearer <token>` to the CRM. The token must
match `WHATSAPP_WEBHOOK_TOKEN` in the CRM's `.env`.

Check the CRM:
```
cd /home/u314035009/neds-crm && grep WHATSAPP_WEBHOOK_TOKEN .env
```
Compare with what wadesk.in has in its own `.env`. They must be identical.
After editing either side, run `php artisan config:cache` on the CRM.

**Check 3 — Is the client's phone number in the CRM?**
The webhook matches the WhatsApp number to a CRM client via phone number. If
the client has no phone number on their CRM record, the ticket is still created
but marked unlinked. Add the phone number to the contact record.

---

## 5. "Open Social Media Dost" / "Open Drishti" SSO button drops to login page

**Symptom:** A portal client clicks the SSO button but lands on the SMDost or
Drishti login page instead of being auto-logged in.

**Check 1 — Is PORTAL_SSO_SECRET loaded in the SMDost container?**
```
ssh root@72.60.98.246
docker compose -f /opt/app/social-media-tool/docker-compose.yml exec app printenv PORTAL_SSO_SECRET
```
If blank: the secret is not in the `environment:` block of the docker-compose.yml.
Fix:
```
sed -i '/NEXTAUTH_SECRET/a\      - PORTAL_SSO_SECRET=d6b758e4c84a0c5c7ca3596554fd18174a0fe64905b0446e6ae5f586dcc62963' /opt/app/social-media-tool/docker-compose.yml
docker compose -f /opt/app/social-media-tool/docker-compose.yml up -d --force-recreate app
```

**Check 2 — Is PORTAL_SSO_SECRET set in the CRM?**
```
cd /home/u314035009/neds-crm && grep PORTAL_SSO_SECRET .env
```
Should show the same 64-character hex value. If missing, add it and run
`php artisan config:cache`.

**Check 3 — Does the client user exist in SMDost?**
```
ssh root@72.60.98.246
docker compose -f /opt/app/social-media-tool/docker-compose.yml exec app node -e "
const { PrismaClient } = require('@prisma/client');
const p = new PrismaClient();
p.user.findMany({ where: { email: 'CLIENT_EMAIL_HERE' }, select: { email: true, role: true } })
  .then(r => console.log(r.length ? JSON.stringify(r) : 'USER NOT FOUND'))
  .finally(() => p.\$disconnect());
"
```
If `USER NOT FOUND`: the client was never provisioned. This happens when a
client was created manually rather than through the deal-Won flow. The deal
must be marked **Won** in the CRM to trigger automatic provisioning. If the
deal is already Won, check the CRM activity feed on that client — a
`system: Provisioned in SMDost` activity should exist. If not, a queue job
may have failed (see Section 7).

**Note:** The "Drishti" button follows the same logic but uses
`/opt/app/agencyos/.env.production` — edit that file and force-recreate
the Drishti container (`docker compose -f /opt/app/agencyos/docker-compose.yml up -d --force-recreate app`).

---

## 6. Client not auto-provisioned in Drishti / SMDost after deal Won

**Symptom:** A deal is marked Won but no `smdost_client_id` / `drishti_client_id`
appears on the client, and no provisioning activity shows in the client's
Activity feed.

**Check 1 — Are the service keys set in all three places?**

On CRM (Hostinger):
```
cd /home/u314035009/neds-crm && grep -E 'DRISHTI_SERVICE_KEY|SMDOST_SERVICE_KEY' .env
```

On SMDost container:
```
ssh root@72.60.98.246
docker compose -f /opt/app/social-media-tool/docker-compose.yml exec app printenv SMDOST_SERVICE_KEY
docker compose -f /opt/app/social-media-tool/docker-compose.yml exec app printenv DRISHTI_SERVICE_KEY
```

On Drishti container:
```
docker compose -f /opt/app/agencyos/docker-compose.yml exec app printenv SERVICE_API_KEY
```

All three must be set and consistent. If any is blank, add it to the relevant
`environment:` block (SMDost/Drishti) or `.env` (CRM), then force-recreate the
container and run `php artisan config:cache` on the CRM.

**Check 2 — Are queued jobs processing?**
The provisioning runs as a background queue job. On Hostinger the queue worker
runs via cron every minute. Check the jobs table:
```
cd /home/u314035009/neds-crm && php artisan queue:monitor
```
Or check for failed jobs:
```
php artisan queue:failed
```
If there are failed jobs, retry them:
```
php artisan queue:retry all
```

---

## 7. Drishti activity events not appearing in CRM client timeline

**Symptom:** Posts are approved or published in Drishti but no activity log
entry appears on the CRM client's page.

**Check — Is the Drishti webhook secret correct?**
```
cd /home/u314035009/neds-crm && grep DRISHTI_WEBHOOK_SECRET .env
```
This must match `WEBHOOK_SECRET` in Drishti's `.env.production`. If changed on
either side, update the other and run `php artisan config:cache` on the CRM
and `docker compose up -d --force-recreate app` on Drishti.

---

## 8. SMDost brief approved but no draft invoice created in CRM

**Symptom:** A brief is fully approved in SMDost but no Draft invoice appears
in the CRM and accounts receive no notification.

**Check — Webhook token match**
```
cd /home/u314035009/neds-crm && grep SMDOST_SERVICE_KEY .env
```
Must match what SMDost sends as the `Authorization: Bearer` token.
Check SMDost:
```
ssh root@72.60.98.246
docker compose -f /opt/app/social-media-tool/docker-compose.yml exec app printenv SMDOST_SERVICE_KEY
```
If mismatched, update the CRM `.env` and run `php artisan config:cache`.

---

## 9. Menu / sidebar showing wrong items after a role change

**Symptom:** A user's sidebar still shows their old role's menu items after
their role was changed in Admin → Users.

**What happened:** The sidebar is cached per user for 24 hours. Changing a role
should flush the cache automatically, but if it doesn't:

Fix (flushes all user menu caches immediately):
```
cd /home/u314035009/neds-crm && php artisan tinker --no-interaction
```
Then:
```php
app(\App\Services\MenuResolver::class)->flush();
```

The user will see the correct menu on their next page load.

---

## 10. Config changes not taking effect after editing .env

**Symptom:** You edited a value in `.env` on the server but the app still
behaves as before (old model, old token, old key).

**Fix — always run this after any .env edit:**
```
cd /home/u314035009/neds-crm && php artisan config:cache
```

**If it still doesn't work (OPcache issue):**
PHP's OPcache can serve stale bytecode even after `config:cache`. The reliable
bypass: set the value directly in `.env` (so `env()` reads it fresh) and
immediately run `config:cache`. If the config file's PHP default is what you
changed (not the env var), touch the config file first:
```
touch config/services.php && php artisan config:cache
```
If it's still stale, wait 5 minutes for OPcache TTL to expire, or ask your
developer to reset OPcache.

---

## 11. Website contact form stopped creating leads

**Symptom:** Someone submits the form on niranjanenterprises.com but no new
lead appears in the CRM.

**Check — Token match**
The Elementor webhook URL contains `?token=...` as a query parameter. This must
match `LEAD_CAPTURE_TOKEN` in the CRM's `.env`:
```
cd /home/u314035009/neds-crm && grep LEAD_CAPTURE_TOKEN .env
```
If changed, update the Elementor webhook URL in WordPress to match (Elementor
→ Form → Actions → Webhook → URL).

---

## 12. Database backup not running or backup email not arriving

**Symptom:** No backup notification email at 2 AM, or
`storage/app/backups/` is empty.

**Check 1 — Is the cron active?**
Verify in hPanel → Cron Jobs that the `schedule:run` cron is still listed and
enabled.

**Check 2 — Manual backup run**
```
cd /home/u314035009/neds-crm && php artisan app:backup-database
```
If it errors, check that `DB_DUMP_BINARY` in `.env` points to the correct
mysqldump path and that `BACKUP_NOTIFY_EMAIL` is set.

**Check 3 — SMTP**
The backup notification uses the same SMTP as all other CRM emails. If other
emails are working, SMTP is fine and the backup itself is the issue. If other
emails are also not sending, fix SMTP first (see Section 3 Check 4).

---

## 13. Bell notifications not appearing

**Symptom:** Staff report they're not receiving bell notifications for events
such as new leads, deal won, payment recorded, or the recurring invoice due
warning.

There are two categories of bell notification:

- **Event-triggered** (new lead, new quotation, deal won, new invoice, payment
  recorded) — fire instantly when the event occurs, no cron needed.
- **Scheduled** (recurring invoice due in 7 days) — fires daily at **08:00 IST**
  via the cron; requires the scheduler to be running.

**Check 1 — For the recurring invoice 7-day warning only: is the command scheduled?**
```
cd /home/u314035009/neds-crm && php artisan schedule:list | grep due-warning
```
Should show `app:send-recurring-invoice-due-warnings` at 08:00 IST. If missing,
check `routes/console.php` and re-deploy. Then verify the Hostinger cron is
active (hPanel → Cron Jobs — see Section 3 Check 3).

**Check 2 — Are notifications being written to the database at all?**
```
cd /home/u314035009/neds-crm && php artisan tinker --no-interaction
```
Then:
```php
DB::table('notifications')->latest('created_at')->take(5)->get(['type','notifiable_id','created_at','read_at']);
```
If the result is empty and events have definitely occurred (payments recorded,
leads created, etc.), check the application log for errors (Check 4 below).

**Check 3 — Check a specific user's unread count:**
```php
\App\Models\User::where('email', 'user@example.com')->first()->unreadNotifications()->count();
```
If this returns a number but the bell shows zero, the user may have already
dismissed them — dismissed notifications are removed from the count. The bell
only shows **unread** (not yet dismissed) notifications.

**Check 4 — Look for exceptions in the log:**
```
tail -100 /home/u314035009/neds-crm/storage/logs/laravel.log | grep -i 'exception\|error\|notification'
```
Any `QueryException` or `InvalidArgumentException` here will explain why a
notification failed to save.

**Note on new lead notifications:** the notification fires only when a lead is
**created** — not when it is edited. Leads created via the CRM form, the website
capture form, and the WhatsApp webhook all trigger the notification. A re-save
(edit) does not.

---

## General: when in doubt, run these four commands

After any deployment, `.env` edit, or unexpected behaviour:
```
cd /home/u314035009/neds-crm
php artisan config:cache
php artisan route:cache
php artisan view:clear && php artisan view:cache
```
And if menu items are involved:
```
php artisan db:seed --class=MenuItemsSeeder --force
```
