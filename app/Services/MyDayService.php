<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\TaskStatus;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Consolidates the scattered "what's due" queries that already exist across
 * separate modules (Tasks, Leads, Deals, Calls, Tickets) into one flat,
 * time-sorted worklist for the logged-in user, so they don't have to
 * app-hop between five screens each morning. Always strictly scoped to the
 * viewer themselves (assignee/owner = $user->id) regardless of role — unlike
 * the Sales Dashboard's "everyone vs just me" split, "My Day" is inherently
 * personal even for Admin/Manager.
 */
class MyDayService
{
    /**
     * @return Collection<int, array{type: string, title: string, subtitle: ?string, when: \Illuminate\Support\Carbon, url: string}>
     */
    public function worklist(User $user): Collection
    {
        $now = now();
        $items = collect();

        Task::query()
            ->assignedTo($user->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $now->copy()->startOfDay())
            ->with('project')
            ->get()
            ->each(function (Task $task) use ($items) {
                $items->push([
                    'type' => 'task',
                    'title' => $task->title,
                    'subtitle' => $task->project?->name ?? 'Standalone task',
                    'when' => $task->due_date,
                    'url' => route('tasks.show', $task),
                ]);
            });

        Lead::query()
            ->where('owner_id', $user->id)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', $now)
            ->whereIn('status', [LeadStatus::New->value, LeadStatus::Contacted->value, LeadStatus::Qualified->value])
            ->get()
            ->each(function (Lead $lead) use ($items) {
                $items->push([
                    'type' => 'lead',
                    'title' => 'Follow up: '.$lead->name,
                    'subtitle' => $lead->company,
                    'when' => $lead->next_follow_up_at,
                    'url' => route('leads.show', $lead),
                ]);
            });

        Deal::query()
            ->where('owner_id', $user->id)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', $now)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->with('customer')
            ->get()
            ->each(function (Deal $deal) use ($items) {
                $items->push([
                    'type' => 'deal',
                    'title' => 'Follow up: '.$deal->title,
                    'subtitle' => $deal->customer?->company_name ?? 'Client removed',
                    'when' => $deal->next_follow_up_at,
                    'url' => route('deals.show', $deal),
                ]);
            });

        CallLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', $now)
            ->with('callable')
            ->get()
            ->each(function (CallLog $call) use ($items) {
                $subject = $call->callable instanceof Customer ? $call->callable->company_name : $call->callable?->name;

                $items->push([
                    'type' => 'call',
                    'title' => 'Call: '.($subject ?? 'Unknown'),
                    'subtitle' => $call->next_action,
                    'when' => $call->follow_up_at,
                    'url' => $call->callable instanceof Customer
                        ? route('clients.show', $call->callable->id)
                        : ($call->callable ? route('leads.show', $call->callable->id) : route('calls.index')),
                ]);
            });

        Ticket::query()
            ->where('assignee_id', $user->id)
            ->open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', $now)
            ->with('customer')
            ->get()
            ->each(function (Ticket $ticket) use ($items) {
                $items->push([
                    'type' => 'ticket',
                    'title' => 'SLA breached: '.$ticket->subject,
                    'subtitle' => $ticket->customer?->company_name,
                    'when' => $ticket->sla_due_at,
                    'url' => route('tickets.show', $ticket),
                ]);
            });

        return $items->sortBy('when')->values();
    }
}
