<?php

namespace App\Console\Commands;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Mail\FollowUpReminder;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendFollowUpReminders extends Command
{
    protected $signature = 'app:send-followup-reminders';

    protected $description = 'Email each user the leads and deals whose follow-up is due (run daily at 09:00 IST).';

    public function handle(): int
    {
        $now = now();

        $leadsByOwner = Lead::query()
            ->whereNotNull('owner_id')
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $now)
            ->whereIn('status', [LeadStatus::New->value, LeadStatus::Contacted->value, LeadStatus::Qualified->value])
            ->get()
            ->groupBy('owner_id');

        $dealsByOwner = Deal::query()
            ->whereNotNull('owner_id')
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $now)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->get()
            ->groupBy('owner_id');

        $ownerIds = $leadsByOwner->keys()->merge($dealsByOwner->keys())->unique();
        $sent = 0;

        foreach ($ownerIds as $ownerId) {
            $user = User::find($ownerId);
            if ($user === null) {
                continue;
            }

            Mail::to($user)->send(new FollowUpReminder(
                $user,
                $leadsByOwner->get($ownerId, collect()),
                $dealsByOwner->get($ownerId, collect()),
            ));
            $sent++;
        }

        $this->info("Sent {$sent} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
