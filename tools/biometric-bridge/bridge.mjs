import 'dotenv/config';
import ZKLib from 'node-zklib';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const logFile = path.join(__dirname, 'bridge.log');

function log(message) {
    const line = `[${new Date().toISOString()}] ${message}`;
    console.log(line);
    fs.appendFileSync(logFile, line + '\n');
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

function isWithinRunWindow(date) {
    const day = date.getDay(); // 0 = Sunday, 6 = Saturday
    if (day === 0) {
        return false;
    }

    const hour = date.getHours() + date.getMinutes() / 60;

    return hour >= RUN_WINDOW_START_HOUR && hour < RUN_WINDOW_END_HOUR;
}

async function main() {
    const now = new Date();
    if (!isWithinRunWindow(now)) {
        log('Outside the office run window (Mon-Sat, 8:30am-7:00pm). Skipping this run.');
        return;
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
        log(`No punches in the last ${daysBack} day(s) on the device. Nothing to forward.`);
        return;
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
    log(`Forwarded ${groups.size} user-day group(s) from ${recent.length} raw punch(es). CRM responded ${response.status}: ${responseText.trim()}`);

    if (!response.ok) {
        process.exitCode = 1;
    }
}

main().catch((error) => {
    log(`ERROR: ${error.message}`);
    process.exitCode = 1;
});
