# Biometric Bridge

Fallback for the eSSL biometric attendance device. The device's own outbound
HTTPS push to `crm.talktonitesh.com` never reaches the server — Hostinger's
edge terminates/redirects the connection before this budget ADMS terminal can
complete a real TLS handshake (see the CRM's `docs/user-guides/troubleshooting.md`
Section 1 and the project's backlog notes for the full investigation).

This script sidesteps that entirely: it runs on a regular office PC on the
same LAN as the device, polls the device directly (the same way the existing
"hitech" billing software already does, at `192.168.1.201:4370`), and
forwards punches to the CRM's existing `/iclock/cdata` webhook itself — a
normal HTTPS request from a normal PC, no embedded-TLS/SNI limitation.

## One-time setup

1. Install [Node.js LTS](https://nodejs.org/) on the office PC if it isn't
   already there (`node -v` to check — needs 18+ for the built-in `fetch`).
2. In this folder:
   ```
   npm install
   copy .env.example .env
   ```
3. Edit `.env` — the defaults already match the known device IP, port, and
   CRM serial; only change something if it's actually different on-site.
4. Test it once by hand:
   ```
   npm start
   ```
   Check the console output and `bridge.log` in this folder. A successful
   run looks like:
   ```
   Forwarded 3 user-day group(s) from 5 raw punch(es). CRM responded 200: OK: 5
   ```
5. Confirm on the CRM side: Admin → Attendance (or ask the office to check
   today's attendance) should show today's check-in/out times updating.

## Running it automatically (Windows Task Scheduler)

The device doesn't push in real time to us anymore, so this needs to run on
a schedule — every 5–10 minutes during office hours is enough.

1. Open **Task Scheduler** → **Create Task** (not "Basic Task", so you get
   the full options).
2. **General** tab: name it `NEDS Biometric Bridge`. Under "Security
   options", pick "Run whether user is logged on or not" if this PC might be
   locked during the day, and check "Run with highest privileges" only if
   you hit permission errors without it.
3. **Triggers** tab → New → "Daily", recur every 1 day, then check "Repeat
   task every: 5 minutes" for a duration of "1 day". Set the daily start
   time to when the office opens.
4. **Actions** tab → New → Action "Start a program":
   - Program/script: full path to `node.exe` (find it with `where node` in
     Command Prompt)
   - Add arguments: `bridge.mjs`
   - Start in: the full path to this folder (e.g.
     `C:\NEDS\biometric-bridge`)
5. Save. Right-click the task → **Run** to test it fires correctly, then
   check `bridge.log`.

## Why re-sending is safe

The CRM's biometric webhook (`BiometricWebhookController::push()`) is
idempotent per day: for a check-in it only updates `check_in_at` if none is
recorded yet or the new one is earlier; for a check-out it only updates if
none is recorded yet or the new one is later. That means this script can
safely re-send the last `DAYS_BACK` days of punches on every single run
(default 3) — there's no local sync-state file to get out of sync, and a
missed run or a restarted PC just catches up automatically next time it
runs, with no risk of overwriting a correct time with a stale one.

## Troubleshooting

- **`Error: connect ECONNREFUSED 192.168.1.201:4370`** — the device isn't
  reachable from this PC. Confirm this PC is on the same office network/VLAN
  as the device, and that "hitech" can still reach it right now too.
- **CRM responds with a non-200 / `Forbidden`** — `DEVICE_SERIAL` in `.env`
  doesn't match `BIOMETRIC_DEVICE_SERIAL` in the server's `.env`. They must
  be identical (`NFZ8243301103` as of this writing).
- **Punches show up but for the wrong user** — the punching employee's
  `device_user_id` isn't set (or is wrong) in Admin → Users → Edit. The
  bridge only forwards the raw device user IDs; the CRM does the ID → staff
  member mapping.
- Nothing here needs the biometric device's own HTTPS/ADMS settings to be
  touched — leave those as they are.
