<?php

namespace App\Console\Commands;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Services\LeaveCoverage;
use Illuminate\Console\Command;

/**
 * Keeps a currently-active leave's covering teammate synced to wadesk.in as
 * a temporary conversation assignee, re-running periodically (rather than
 * only once at approval time) so a lead created or reassigned to the
 * leave-taker mid-leave also gets covered — not just the leads open at the
 * moment of approval.
 *
 * Deliberately does NOT need a companion "expire" command: each dispatched
 * job recomputes coverUntil fresh from the leave request's live state (see
 * SyncLeaveCoverToWadeskJob), so once a request's end_date has passed it
 * simply stops matching the query below and the cover it already set
 * expires on its own on wadesk.in's side.
 */
class SyncLeaveCoverToWadesk extends Command
{
    protected $signature = 'app:sync-leave-cover-to-wadesk';

    protected $description = 'Re-sync every currently-active approved leave request\'s covering teammate to wadesk.in, covering any lead assigned to the leave-taker since approval (run every 30 minutes via scheduler).';

    public function handle(LeaveCoverage $coverage): int
    {
        $today = today(config('app.display_timezone'))->toDateString();

        $active = LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Approved)
            ->whereNotNull('covering_user_id')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with('user')
            ->get();

        foreach ($active as $leaveRequest) {
            $coverage->dispatchSync($leaveRequest);
        }

        $this->info("Synced wadesk.in cover for {$active->count()} active leave request(s).");

        return self::SUCCESS;
    }
}
