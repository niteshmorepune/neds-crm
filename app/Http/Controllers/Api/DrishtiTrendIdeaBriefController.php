<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DrishtiTrendIdeaBriefController
{
    /**
     * Maps Drishti's Platform enum to the human-readable platform name
     * SMDost's /api/briefs endpoint expects (same names CreateMonthlyBriefs
     * already sends successfully). An unmapped platform (e.g. YOUTUBE,
     * PINTEREST) is passed through as-is — SMDost will reject it if it
     * doesn't recognize it, surfaced back to Drishti as smdost_error.
     */
    private const PLATFORM_MAP = [
        'INSTAGRAM' => 'Instagram',
        'FACEBOOK' => 'Facebook',
        'LINKEDIN' => 'LinkedIn',
        'TWITTER' => 'Twitter',
        'TIKTOK' => 'TikTok',
        'GBP' => 'Google Business',
    ];

    /**
     * Turns an approved Drishti AI trend idea into an SMDost content brief —
     * the fast-follow deferred when trend ideas shipped (see docs/user-guides
     * and the Drishti trend-ideas feature notes). Drishti calls this when
     * staff click "Send to SMDost" on an idea; the CRM is the only place
     * that already knows both a client's drishti_client_id and
     * smdost_client_id, so it brokers the call rather than teaching Drishti
     * about SMDost client ids directly.
     *
     * Auth: HMAC-SHA256 signature (VerifyDrishtiWebhookSignature), same as
     * the existing Drishti event webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'drishti_client_id' => ['required', 'string'],
            'content_idea_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string'],
            'hook' => ['nullable', 'string'],
            'outline' => ['nullable', 'string'],
            'trend_rationale' => ['nullable', 'string'],
            'source_refs' => ['nullable', 'array'],
            'source_refs.*' => ['string'],
        ]);

        $customer = Customer::where('drishti_client_id', $data['drishti_client_id'])->first();

        if (! $customer) {
            return response()->json(['status' => 'no_customer_match'], 404);
        }

        if (! $customer->smdost_client_id) {
            return response()->json(['status' => 'not_linked_to_smdost'], 422);
        }

        $alreadySent = Activity::where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->where('event', 'smdost_brief_created')
            ->whereJsonContains('changes->content_idea_id', $data['content_idea_id'])
            ->exists();

        if ($alreadySent) {
            return response()->json(['status' => 'already_sent']);
        }

        $baseUrl = rtrim((string) config('services.smdost.base_url'), '/');
        $serviceKey = (string) config('services.smdost.service_key');

        if (! $baseUrl || ! $serviceKey) {
            return response()->json(['status' => 'smdost_not_configured'], 500);
        }

        $platformName = self::PLATFORM_MAP[strtoupper($data['platform'])] ?? $data['platform'];
        $description = collect([$data['hook'] ?? null, $data['outline'] ?? null])
            ->filter()
            ->implode("\n\n");

        $campaignDescription = 'Sent from a Drishti AI trend idea. Review before generating content.';
        if (! empty($data['trend_rationale'])) {
            $campaignDescription .= "\n\nWhy now: {$data['trend_rationale']}";
        }
        if (! empty($data['source_refs'])) {
            $campaignDescription .= "\n\nSources: ".implode(', ', $data['source_refs']);
        }

        try {
            $response = Http::withHeader('X-Service-Key', $serviceKey)
                ->timeout(15)
                ->post("{$baseUrl}/api/briefs", [
                    'clientId' => $customer->smdost_client_id,
                    'title' => $data['title'],
                    'contentGoal' => $description ?: $data['title'],
                    'campaignDescription' => $campaignDescription,
                    'scheduledMonth' => now()->startOfMonth()->toIso8601String(),
                    'platforms' => [
                        ['platform' => $platformName, 'contentType' => 'IMAGE', 'postsCount' => 1],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('DrishtiTrendIdeaBrief: exception calling SMDost', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'smdost_error'], 502);
        }

        if (! $response->successful()) {
            Log::warning('DrishtiTrendIdeaBrief: SMDost brief creation failed', [
                'customer_id' => $customer->id,
                'status' => $response->status(),
            ]);

            return response()->json(['status' => 'smdost_error'], 502);
        }

        $briefId = $response->json('id');

        Activity::create([
            'user_id' => null,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'event' => 'smdost_brief_created',
            'changes' => [
                'source' => 'drishti_trend_idea',
                'content_idea_id' => $data['content_idea_id'],
                'brief_id' => $briefId,
                'title' => $data['title'],
            ],
        ]);

        return response()->json(['status' => 'ok', 'brief_id' => $briefId]);
    }
}
