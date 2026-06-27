<?php

namespace App\Console\Commands;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\TicketStatus;
use App\Mail\MorningDigest;
use App\Models\CallLog;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendMorningDigest extends Command
{
    protected $signature = 'app:send-morning-digest';

    protected $description = 'Email each active user their personalised day-ahead digest (run at 09:00 IST).';

    public function handle(): int
    {
        $tz = config('app.display_timezone');
        $today = Carbon::today($tz);

        if ($today->isSunday()) {
            $this->info('Sunday — skipping morning digest.');

            return self::SUCCESS;
        }

        $endOfToday = $today->copy()->endOfDay();

        $openLeadStatuses = [LeadStatus::New->value, LeadStatus::Contacted->value, LeadStatus::Qualified->value];
        $closedDealStages = [DealStage::Won->value, DealStage::Lost->value];
        $openTicketStatuses = [TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::Waiting->value];

        $users = User::where('is_active', true)->get();
        $sent = 0;

        foreach ($users as $user) {
            $overdueTasks = Task::where('assignee_id', $user->id)
                ->where('status', '!=', \App\Enums\TaskStatus::Done->value)
                ->whereNotNull('due_date')
                ->where('due_date', '<', $today->toDateString())
                ->with('project')
                ->orderBy('due_date')
                ->get();

            $dueTodayTasks = Task::where('assignee_id', $user->id)
                ->where('status', '!=', \App\Enums\TaskStatus::Done->value)
                ->whereDate('due_date', $today->toDateString())
                ->with('project')
                ->orderBy('priority')
                ->get();

            $callFollowUps = CallLog::where('user_id', $user->id)
                ->whereNotNull('follow_up_at')
                ->where('follow_up_at', '<=', $endOfToday)
                ->whereNull('follow_up_notified_at')
                ->with('callable')
                ->orderBy('follow_up_at')
                ->get();

            $leadFollowUps = Lead::where('owner_id', $user->id)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', $endOfToday)
                ->whereIn('status', $openLeadStatuses)
                ->orderBy('next_follow_up_at')
                ->get();

            $dealFollowUps = Deal::where('owner_id', $user->id)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', $endOfToday)
                ->whereNotIn('stage', $closedDealStages)
                ->with('customer')
                ->orderBy('next_follow_up_at')
                ->get();

            $openTickets = Ticket::where('assignee_id', $user->id)
                ->whereIn('status', $openTicketStatuses)
                ->with('customer')
                ->orderByRaw("CASE WHEN sla_due_at IS NULL THEN 1 ELSE 0 END, sla_due_at ASC")
                ->get();

            $digest = new MorningDigest(
                user: $user,
                date: $today,
                overdueTasks: $overdueTasks,
                dueTodayTasks: $dueTodayTasks,
                callFollowUps: $callFollowUps,
                leadFollowUps: $leadFollowUps,
                dealFollowUps: $dealFollowUps,
                openTickets: $openTickets,
            );

            if ($digest->isEmpty()) {
                continue;
            }

            Mail::to($user)->send($digest);
            $sent++;
        }

        $this->info("Sent morning digest to {$sent} user(s).");

        return self::SUCCESS;
    }
}
