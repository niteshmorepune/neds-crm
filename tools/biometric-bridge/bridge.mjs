import 'dotenv/config';
import ZKLib from 'node-zklib';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const logFile = path.join(__dirname, 'bridge.log');

// Only auto-run/install a crash handler when executed directly
// (`node bridge.mjs`), not when imported by check-manual-sync.mjs for its
// runSync() call — that script installs its own handler instead, so it can
// report the failure back to the CRM before exiting. Computed here (not just
// near main() below) so the guard around the handler registration further
// down can use it.
const isMain = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;

export function log(message) {
    const line = `[${new Date().toISOString()}] ${message}`;
    console.log(line);
    fs.appendFileSync(logFile, line + '\n');
}

if (isMain) {
    // node-zklib has a bug: when a device-data request fails, readWithBuffer()
    // calls reject(err) without returning, then dereferences the still-null
    // reply — a second throw inside its `new Promise(async ...)` executor that
    // never gets attached to anything. Node treats that as fatal and kills the
    // process before our own try/catch in main() ever runs. Intercept it here so
    // a device-side hiccup (stale session, device unreachable mid-read, etc.)
    // logs cleanly and exits instead of dumping a raw stack trace with no log
    // entry at all.
    process.on('unhandledRejection', (reason) => {
        const message = reason instanceof Error ? reason.message : String(reason);
        log(`ERROR (unhandled rejection, likely a node-zklib device-read failure): ${message}`);
        process.exit(1);
    });
}

function required(name) {
    const value = process.env[name];
    if (!value) {
        throw new Error(`Missing required env var: ${name}`);
    }
    return value;
}

// Device punches carry the device's own wall-clock reading (already IST,
// same as the office PC this script runs on) — format using local getters,
// not UTC, so the time sent to the CRM matches what the device itself saw.
function formatLocal(date) {
    const p = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())} ${p(date.getHours())}:${p(date.getMinutes())}:${p(date.getSeconds())}`;
}

function localDateKey(date) {
    const p = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`;
}

// Office is 9am-6pm, Monday-Saturday. The Task Scheduler trigger is set to
// this window too, but a PC can wake from sleep, a trigger can misfire, or
// someone can run `npm start` by hand — this is a backstop, not the primary
// control. Padded past both edges (8:30am-7pm) so a genuinely early arrival
// or late finisher still gets picked up on the next in-window run.
const RUN_WINDOW_START_HOUR = 8.5; // 8:30am
const RUN_WINDOW_END_HOUR = 19; // 7:00pm
const HOLIDAYS_FILE = path.join(__dirname, 'holidays.json');

// Read fresh on every run (not cached at module load) so editing the file
// takes effect on the very next 5-minute tick without restarting anything.
function loadHolidays() {
    try {
        const parsed = JSON.parse(fs.readFileSync(HOLIDAYS_FILE, 'utf8'));
        return new Set(parsed.dates ?? []);
    } catch (error) {
        log(`WARNING: could not read holidays.json (${error.message}) — treating today as a normal working day.`);
        return new Set();
    }
}

// Returns null when it's fine to run, otherwise a reason string for the log.
export function skipReason(date) {
    if (date.getDay() === 0) { // 0 = Sunday
        return 'Sunday';
    }

    if (loadHolidays().has(localDateKey(date))) {
        return 'office holiday';
    }

    const hour = date.getHours() + date.getMinutes() / 60;
    if (hour < RUN_WINDOW_START_HOUR || hour >= RUN_WINDOW_END_HOUR) {
        return 'outside 8:30am-7:00pm';
    }

    return null;
}

/**
 * Connects to the device, pulls recent punches, and forwards them to the
 * CRM's ADMS webhook. Shared by the normal scheduled run (bridge.mjs, every
 * 5 minutes) and the manual "Sync now" check (check-manual-sync.mjs, every
 * minute) so both go through identical logic — no separate code path to
 * drift out of sync.
 *
 * Never throws for expected/handled outcomes (skip, device error, CRM
 * rejection) — always returns a result object instead, so callers can report
 * a clean status back to the CRM without needing their own try/catch for
 * every failure mode. A node-zklib internal bug can still kill the process
 * via an unhandled rejection outside this function's control (see the
 * process-level handler above); callers that need to survive that must
 * install their own handler too.
 *
 * @param {boolean} ignoreWindow Skip the office-hours/holiday guard. Used by
 *   a manual sync request — an admin clicking "Sync now" is an explicit,
 *   deliberate action, not a background tick, so it should run immediately
 *   even outside normal hours rather than silently no-op.
 * @returns {Promise<{ok: boolean, skipped: boolean, reason?: string, summary?: string, error?: string}>}
 */
export async function runSync({ ignoreWindow = false } = {}) {
    const now = new Date();
    const reason = ignoreWindow ? null : skipReason(now);
    if (reason) {
        return { ok: true, skipped: true, reason };
    }

    const deviceIp = required('DEVICE_IP');
    const devicePort = Number(process.env.DEVICE_PORT || 4370);
    const crmUrl = required('CRM_URL');
    const deviceSerial = required('DEVICE_SERIAL');
    const daysBack = Number(process.env.DAYS_BACK || 3);

    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - (daysBack - 1));
    cutoff.setHours(0, 0, 0, 0);

    const zk = new ZKLib(deviceIp, devicePort, 10000, 4000);

    let logs;
    try {
        await zk.createSocket();

        // node-zklib's getAttendances() has a bug: when the device reports
        // exactly 0 records, it dereferences a null reply instead of
        // returning an empty list, which is what triggers the "unhandled
        // rejection" crash this module guards against above. That crash is
        // not a device fault — it's genuinely just "no data right now" (the
        // device's local buffer can empty out on its own, or via the
        // "hitech" billing software polling/clearing the same terminal).
        // Check first via getInfo() so we can return the correct "nothing to
        // forward" result instead of ever calling the buggy path.
        const info = await zk.getInfo();
        if (info.logCounts === 0) {
            return { ok: true, skipped: false, summary: 'No punches on the device right now (logCounts: 0). Nothing to forward.' };
        }

        const result = await zk.getAttendances();
        logs = result.data;
    } finally {
        try {
            await zk.disconnect();
        } catch {
            // already disconnected / never connected — nothing to clean up
        }
    }

    const recent = logs.filter((r) => new Date(r.recordTime) >= cutoff);

    if (recent.length === 0) {
        return { ok: true, skipped: false, summary: `No punches in the last ${daysBack} day(s) on the device. Nothing to forward.` };
    }

    // Group by device_user_id + calendar day, then collapse to first/last
    // punch of that day — matches how the device's own ADMS push behaves,
    // and how the CRM webhook interprets status 0 (entry) / 1 (exit).
    const groups = new Map();
    for (const record of recent) {
        const time = new Date(record.recordTime);
        const key = `${record.deviceUserId}|${localDateKey(time)}`;
        if (!groups.has(key)) {
            groups.set(key, []);
        }
        groups.get(key).push(time);
    }

    const lines = ['ATTLOG'];
    for (const [key, times] of groups) {
        const [deviceUserId] = key.split('|');
        times.sort((a, b) => a - b);

        const checkIn = times[0];
        lines.push(`${deviceUserId}\t${formatLocal(checkIn)}\t0\t1\t0\t0\t0`);

        if (times.length > 1) {
            const checkOut = times[times.length - 1];
            lines.push(`${deviceUserId}\t${formatLocal(checkOut)}\t1\t1\t0\t0\t0`);
        }
    }

    const body = lines.join('\n') + '\n';
    const url = `${crmUrl}?SN=${encodeURIComponent(deviceSerial)}&table=ATTLOG&Stamp=9999`;

    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain' },
        body,
    });

    const responseText = await response.text();
    const summary = `Forwarded ${groups.size} user-day group(s) from ${recent.length} raw punch(es). CRM responded ${response.status}: ${responseText.trim()}`;

    if (!response.ok) {
        return { ok: false, skipped: false, error: summary };
    }

    return { ok: true, skipped: false, summary };
}

async function main() {
    const result = await runSync();

    if (result.skipped) {
        log(`Skipping this run (${result.reason}).`);
        return;
    }

    if (result.ok) {
        log(result.summary);
    } else {
        log(result.error);
        process.exitCode = 1;
    }
}

if (isMain) {
    main().catch((error) => {
        log(`ERROR: ${error.message}`);
        process.exitCode = 1;
    });
}
