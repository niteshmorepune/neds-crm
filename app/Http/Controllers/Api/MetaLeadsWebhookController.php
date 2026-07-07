<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportMetaLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaLeadsWebhookController extends Controller
{
    /**
     * GET handshake Meta calls once when the webhook subscription is
     * registered (and periodically to re-verify). Must echo back
     * hub.challenge as plain text when hub.verify_token matches — PHP
     * receives Meta's dotted query keys (hub.mode etc.) as hub_mode,
     * hub_verify_token, hub_challenge (dots become underscores in $_GET).
     */
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.meta.webhook_verify_token');
        $token = (string) $request->query('hub_verify_token', '');
        $mode = $request->query('hub_mode');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($expected !== '' && $mode === 'subscribe' && hash_equals($expected, $token)) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * POST event Meta calls when a new lead is submitted. The payload only
     * carries a leadgen_id per change, never the submitted field data — each
     * one is queued for ImportMetaLead to fetch via the Graph API. Responds
     * immediately so Meta doesn't retry due to a slow response.
     */
    public function receive(Request $request): JsonResponse
    {
        $dispatched = 0;

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $leadgenId = $change['value']['leadgen_id'] ?? null;

                if ($leadgenId) {
                    ImportMetaLead::dispatch((string) $leadgenId);
                    $dispatched++;
                }
            }
        }

        return response()->json(['status' => 'ok', 'dispatched' => $dispatched]);
    }
}
