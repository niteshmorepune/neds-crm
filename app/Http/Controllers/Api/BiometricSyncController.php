<?php

namespace App\Http\Controllers\Api;

use App\Enums\BiometricSyncStatus;
use App\Http\Controllers\Controller;
use App\Models\BiometricSyncRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Polled/reported by the office-LAN bridge script (tools/biometric-bridge/
 * check-manual-sync.mjs), not a browser — see VerifyBiometricBridgeToken.
 * The CRM can't reach the device directly (it only lives on the office LAN),
 * so a manual "Sync now" click just queues a request here; the bridge script
 * checks for one every minute and reports back when it's done.
 */
class BiometricSyncController extends Controller
{
    public function pending(): JsonResponse
    {
        $request = BiometricSyncRequest::where('status', BiometricSyncStatus::Pending)
            ->oldest('requested_at')
            ->first();

        return response()->json([
            'pending' => (bool) $request,
            'id' => $request?->id,
        ]);
    }

    public function complete(Request $request, BiometricSyncRequest $syncRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([BiometricSyncStatus::Completed->value, BiometricSyncStatus::Failed->value])],
            'summary' => ['nullable', 'string', 'max:500'],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        $syncRequest->update([
            'status' => $data['status'],
            'summary' => $data['summary'] ?? null,
            'error' => $data['error'] ?? null,
            'completed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
