<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrishtiWebhookController
{
    /**
     * Receive a webhook event from nedsdrishti.in and write it to the CRM
     * activity feed on the matching customer.
     *
     * Auth: HMAC-SHA256 signature verified upstream by
     *       VerifyDrishtiWebhookSignature middleware.
     *
     * Payload shape from Drishti:
     *   { "event": "post.approved", "timestamp": 1234567890, "data": { ... } }
     */
    public function handle(Request $request): JsonResponse
    {
        $event           = (string) $request->header('X-Agency-Event', '');
        $data            = $request->input('data', []);
        $drishtiClientId = $data['clientId'] ?? null;

        if (! $drishtiClientId) {
            return response()->json(['status' => 'ignored', 'reason' => 'no_client_id']);
        }

        $customer = Customer::where('drishti_client_id', $drishtiClientId)->first();

        if (! $customer) {
            return response()->json(['status' => 'no_customer_match']);
        }

        $changes = match ($event) {
            'post.approved'  => [
                'drishti_event' => 'post.approved',
                'post_id'       => $data['postId'] ?? null,
                'platforms'     => $data['platforms'] ?? [],
            ],
            'post.rejected'  => [
                'drishti_event' => 'post.rejected',
                'post_id'       => $data['postId'] ?? null,
            ],
            'post.published' => [
                'drishti_event' => 'post.published',
                'post_id'       => $data['postId'] ?? null,
                'platforms'     => $data['platforms'] ?? [],
            ],
            default => null,
        };

        if ($changes === null) {
            return response()->json(['status' => 'ignored', 'reason' => 'unknown_event']);
        }

        Activity::create([
            'user_id'      => null,
            'subject_type' => Customer::class,
            'subject_id'   => $customer->id,
            'event'        => 'updated',
            'changes'      => $changes,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
