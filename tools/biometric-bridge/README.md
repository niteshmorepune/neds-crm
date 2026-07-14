# Biometric Bridge

Fallback for the eSSL biometric attendance device. The device's own outbound
HTTPS push to `crm.niranjanenterprises.co.in` never reaches the server — Hostinger's
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
a schedule — every 5–10 minutes during office hours is enough. Office hours
are 9am–6pm, Monday–Saturday, so the schedule below runs from 8:30am to
7:00pm on those days (a little padding on each side so an early arrival or a
late checkout still gets caught) and doesn't bother polling the device at
night or on Sunday when no one's there.

1. Open **Task Scheduler** → **Create Task** (not "Basic Task", so you get
   the full options).
2. **General** tab: name it `NEDS Biometric Bridge`. Under "Security
   options", pick "Run whether user is logged on or not" if this PC might be
   locked during the day, and check "Run with highest privileges" only if
   you hit permission errors without it.
3. **Triggers** tab → New → "Weekly", check Monday through Saturday (leave
   Sunday unchecked), then check "Repeat task every: 5 minutes" for a
   duration of "10 hours 30 minutes". Set the start time to 8:30 AM.
4. **Actions** tab → New → Action "Start a program":
   - Program/script: full path to `node.exe` (find it with `where node` in
     Command Prompt)
   - Add arguments: `bridge.mjs`
   - Start in: the full path to this folder (e.g.
     `C:\NEDS\biometric-bridge`)
5. Save. Right-click the task → **Run** to test it fires correctly, then
   check `bridge.log`.

`bridge.mjs` also enforces this window itself (Mon–Sat, 8:30am–7:00pm) and
exits without polling the device if it's invoked outside it — a backstop for
if the PC wakes from sleep and the trigger fires late, or someone runs
`npm start` by hand outside hours, not the primary schedule control.

It also skips office holidays, listed by date in `holidays.json` in this
folder (e.g. `{"dates": ["2026-08-15", "2026-10-20"]}`). Task Scheduler has
no holiday-calendar concept, so this list is read fresh from disk on every
run — add next year's dates to it once a year, no restart needed.

## Manual "Sync now" (from the CRM's Attendance page)

Admins/managers can click **Sync from biometric** on the Attendance page
instead of waiting for the next 5-minute scheduled run. The CRM has no
network path into the office LAN (it's on Hostinger, the device is only
reachable from here), so clicking the button doesn't reach the device
directly — it just queues a request. `check-manual-sync.mjs`, running as a
separate Scheduled Task every minute, polls the CRM for a pending request and
runs the same sync logic immediately (ignoring the office-hours window,
since a manual click is a deliberate action) when it finds one, then reports
the outcome back so the button's status line updates ("Synced 40 seconds
ago" / "Sync failed: ..."). Setup:

1. Same one-time setup as above, plus two more `.env` values:
   `CRM_BASE_URL` (plain site root, not the `/iclock/cdata` URL) and
   `BIOMETRIC_BRIDGE_TOKEN` (must match the CRM server's own
   `BIOMETRIC_BRIDGE_TOKEN` — a different secret from `DEVICE_SERIAL`, which
   authenticates the device's push, not this script).
2. Register a second Scheduled Task (same **Create Task** dialog as above):
   name it `NEDS Biometric Bridge - Manual Sync Check`, same "Run whether
   user is logged on or not" security option, **Triggers** → New → "Daily",
   repeat every 1 minute, no end date (this one runs all day, not just
   office hours — it's a cheap check, and someone might click the button
   after hours). **Actions** → Start a program → same `node.exe` path, but
   arguments `check-manual-sync.mjs`.
3. Test it: click **Sync from biometric** on the CRM, then within a minute
   check `bridge.log` for a `Manual sync requested (id=N) — running now.`
   line followed by its outcome, and refresh the Attendance page to see the
   status line update.

This script is quiet on purpose when there's nothing to do — it only writes
to `bridge.log` when it actually finds and processes a pending request, so it
doesn't add a line every single minute.

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
- **`ERROR (unhandled rejection, likely a node-zklib device-read failure): Cannot read properties of null (reading 'subarray')`** —
  this was a real bug in `node-zklib`'s `getAttendances()`: it crashes instead
  of returning an empty list when the device reports exactly 0 records
  (`logCounts: 0`, which happens on its own as the device's local buffer
  empties, or via "hitech" polling/clearing the same terminal). Fixed by
  checking `zk.getInfo().logCounts` first and skipping straight to "nothing
  to forward" when it's 0, instead of ever calling the buggy path. If this
  error reappears despite that guard, it's a genuinely different failure —
  don't assume it's the same known issue without checking `logCounts` again.
