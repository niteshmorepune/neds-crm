import 'dotenv/config';
import { log, runSync } from './bridge.mjs';

// Tracks the sync request currently being handled, so the unhandledRejection
// handler below (needed for the same node-zklib bug bridge.mjs guards
// against — see its comment) can still report failure back to the CRM
// instead of just dying silently mid-request.
let activeRequestId = null;
let crashed = false;

process.on('unhandledRejection', async (reason) => {
    crashed = true;
    const message = reason instanceof Error ? reason.message : String(reason);
    log(`Manual sync ERROR (unhandled rejection, likely a node-zklib device-read failure): ${message}`);

    if (activeRequestId !== null) {
        await reportOutcome(activeRequestId, { ok: false, error: message }).catch(() => {
            // best-effort only — we're already mid-crash, don't let a failed
            // report hang the process further.
        });
    }

    process.exit(1);
});

function required(name) {
    const value = process.env[name];
    if (!value) {
        throw new Error(`Missing required env var: ${name}`);
    }
    return value;
}

async function fetchPending(baseUrl, token) {
    const response = await fetch(`${baseUrl}/api/biometric-sync/pending`, {
        headers: { Authorization: `Bearer ${token}` },
    });

    if (!response.ok) {
        throw new Error(`Pending-check failed: CRM responded ${response.status}`);
    }

    return response.json();
}

async function reportOutcome(id, result) {
    const baseUrl = required('CRM_BASE_URL');
    const token = required('BIOMETRIC_BRIDGE_TOKEN');

    const body = result.ok
        ? { status: 'completed', summary: result.summary }
        : { status: 'failed', error: result.error };

    const response = await fetch(`${baseUrl}/api/biometric-sync/${id}/complete`, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        log(`Manual sync (id=${id}): failed to report outcome back to CRM (responded ${response.status}).`);
    }
}

async function main() {
    const baseUrl = required('CRM_BASE_URL');
    const token = required('BIOMETRIC_BRIDGE_TOKEN');

    const { pending, id } = await fetchPending(baseUrl, token);

    if (!pending) {
        // Quiet on purpose — this runs every minute, and most minutes have
        // nothing to do. Only log when there's an actual request to handle.
        return;
    }

    activeRequestId = id;
    log(`Manual sync requested (id=${id}) — running now.`);

    // ignoreWindow: true — an admin clicking "Sync now" is an explicit,
    // deliberate action. Silently no-op'ing because it's 9pm would just
    // leave the request stuck "pending" with no useful signal why.
    const result = await runSync({ ignoreWindow: true });

    if (result.skipped) {
        // Shouldn't happen with ignoreWindow: true, but stay defensive.
        await reportOutcome(id, { ok: false, error: `Skipped (${result.reason}).` });
        log(`Manual sync (id=${id}) skipped: ${result.reason}.`);
        return;
    }

    await reportOutcome(id, result.ok ? { ok: true, summary: result.summary } : { ok: false, error: result.error });
    log(`Manual sync (id=${id}) ${result.ok ? 'completed' : 'failed'}: ${result.ok ? result.summary : result.error}`);

    activeRequestId = null;
}

main().catch((error) => {
    // If the unhandledRejection handler above already fired (and is in the
    // middle of exiting), don't also log a second, less useful message —
    // main()'s own promise rejecting is a side effect of that same crash,
    // not a separate failure.
    if (crashed) {
        return;
    }
    log(`Manual sync check ERROR: ${error.message}`);
    process.exitCode = 1;
});
