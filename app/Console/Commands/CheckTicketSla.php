<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\SlaEscalation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckTicketSla extends Command
{
    protected $signature = 'app:check-ticket-sla';

    protected $description = 'Find open tickets past their SLA and escalate them to managers (run periodically).';

    public function handle(): int
    {
        $breached = Ticket::query()
            ->open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNull('sla_breach_notified_at')
            ->with(['customer', 'assignee'])
            ->get();

        if ($breached->isEmpty()) {
            $this->info('No new SLA breaches.');

            return self::SUCCESS;
        }

        $managers = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        foreach ($managers as $manager) {
            Mail::to($manager)->send(new SlaEscalation($breached));
        }

        Ticket::whereIn('id', $breached->pluck('id'))->update(['sla_breach_notified_at' => now()]);

        $this->info("Escalated {$breached->count()} breached ticket(s) to {$managers->count()} manager(s).");

        return self::SUCCESS;
    }
}
