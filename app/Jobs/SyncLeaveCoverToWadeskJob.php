<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Grants (or expires) a leave request's covering teammate temporary
 * visibility into one Lead's wadesk.in WhatsApp conversation, via
 * wadesk.in's POST /api/leads/set-cover.
 *
 * Recomputes coverUntil fresh from the leave request's LIVE state every
 * time it runs, rather than trusting whatever was true when it was
 * dispatched — so re-running this exact same job (App\Services\
 * LeaveCoverage::dispatchSync(), called both immediately on approval and
 * periodically by app:sync-leave-cover-to-wadesk) always converges to the
 * correct outcome: coverUntil = the leave's end-of-day end_date (Asia/
 * Kolkata) while the request is Approved and currently active, or "now"
 * (immediate expiry) the moment it no longer is — covers the request
 * having since ended, with no separate "revoke" code path needed.
 *
 * No-ops (logs, never throws) whenever wadesk config, the lead's phone, or
 * the covering user is absent/inactive. Same failure-tolerance discipline
 * as SyncLeadToWadeskJob — a wadesk.in outage must never block leave
 * approval.
 */
class SyncLeaveCoverToWadeskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $leadId, public int $leaveRequestId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $marketingNumber = (string) config('services.wadesk.marketing_number');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber) {
            return;
        }

        $lead = Lead::find($this->leadId);
        $leaveRequest = LeaveRequest::find($this->leaveRequestId);

        if ($lead === null || blank($lead->phone) || $leaveRequest === null || $leaveRequest->covering_user_id === null) {
            return;
        }

        $coveringUser = $leaveRequest->coveringUser;

        if ($coveringUser === null || ! $coveringUser->is_active) {
            return;
        }

        // Constructed via createFromFormat against the display timezone,
        // never a naive parse of a UTC-cast date attribute — same fix
        // pattern as the Create Meeting timezone bug (see CLAUDE.md).
        $coverUntil = $leaveRequest->isCurrentlyActive()
            ? Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $leaveRequest->end_date->toDateString().' 23:59:59',
                config('app.display_timezone', 'Asia/Kolkata')
            )->utc()
            : now();

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/leads/set-cover", [
                    'phone' => Phone::digits($lead->phone),
                    'businessNumber' => $marketingNumber,
                    'coveringAgentEmail' => $coveringUser->email,
                    'coverUntil' => $coverUntil->toIso8601String(),
                ]);

            if (! $response->successful()) {
                Log::warning('SyncLeaveCoverToWadeskJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'leave_request_id' => $this->leaveRequestId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SyncLeaveCoverToWadeskJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'leave_request_id' => $this->leaveRequestId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
