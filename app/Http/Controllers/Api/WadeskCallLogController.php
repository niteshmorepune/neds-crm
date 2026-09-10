<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * wadesk.in -> CRM bridge: syncs an ANSWERED WhatsApp voice call (Meta
 * Cloud API Calling, inbound only) into the CRM's own CallLog, so it
 * counts toward employee performance reports the same way a manually
 * logged phone call does. Owner-requested 2026-09-10, once inbound
 * calling was confirmed working end-to-end.
 *
 * Deliberately answered-calls-only (wadesk.in's own
 * syncCompletedCallToCrm() never calls this for a missed/declined/failed
 * call) -- call_logs.user_id is required (not nullable), and only a call
 * someone actually answered has an unambiguous person to attribute it to.
 * `agent_email` is matched against this CRM's own User.email -- a wadesk
 * agent whose login doesn't share an email with a real CRM user (e.g. a
 * test/admin account) silently doesn't get logged here, same
 * "never guess an attribution" contract every other wadesk<->CRM bridge
 * in this app already follows. Dedup via `wadesk_call_id`
 * (wadesk.in's own Call.metaCallId) -- a retried delivery of the same
 * call is a harmless no-op, never a duplicate row.
 */
class WadeskCallLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'agent_email' => ['required', 'string', 'email'],
            'wadesk_call_id' => ['required', 'string'],
            'started_at' => ['required', 'date'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
        ]);

        if (CallLog::where('wadesk_call_id', $data['wadesk_call_id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $user = User::where('email', $data['agent_email'])->first();

        if ($user === null) {
            return response()->json(['status' => 'no_matching_user']);
        }

        $customer = Customer::findByPhone($data['phone']);
        $lead = $customer === null ? Lead::findOpenByPhone($data['phone']) : null;

        [$callableType, $callableId] = match (true) {
            $customer !== null => [Customer::class, $customer->id],
            $lead !== null => [Lead::class, $lead->id],
            default => [null, null],
        };

        $callLog = CallLog::create([
            'user_id' => $user->id,
            'callable_type' => $callableType,
            'callable_id' => $callableId,
            'direction' => CallDirection::Incoming->value,
            'outcome' => CallOutcome::Connected->value,
            'duration_minutes' => (int) round($data['duration_seconds'] / 60),
            'notes' => 'Synced from a WhatsApp voice call (wadesk.in).',
            'called_at' => Carbon::parse($data['started_at'])->utc(),
            'wadesk_call_id' => $data['wadesk_call_id'],
        ]);

        return response()->json(['status' => 'created', 'call_log_id' => $callLog->id]);
    }
}
