<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Assembles a per-employee "what did they do" chronological feed and a
 * "what's still open" snapshot, for Employee 360° (Admin/Manager viewing
 * anyone) and the Daily Reports page (self-view). Deliberately built as pure
 * aggregation over data this app already captures — the `activities` audit
 * log (who/what/when via the LogsActivity trait, already on ~30 core
 * models) plus CallLog (calls aren't activity-logged) — no new columns, no
 * new capture step for staff to remember.
 */
class EmployeeActivityTimeline
{
    /**
     * Subject types worth surfacing on a work timeline. Deliberately a
     * curated allow-list, not "every activities row" — excludes internal
     * admin-config models (MenuItem, Subscription, Festival, TeamNudge,
     * Announcement, LeadAssignmentRule, ...) that LogsActivity also tracks
     * but aren't "employee work" in the sense the owner asked for.
     *
     * @var list<class-string>
     */
    private const TRACKED_SUBJECTS = [
        Deal::class, Quotation::class, Invoice::class, Payment::class,
        Ticket::class, Task::class, Lead::class, Customer::class, Project::class,
    ];

    /**
     * Chronological "what they did" feed, most recent first — one entry per
     * activities row (in the tracked subject list) plus one per call logged
     * in the range.
     *
     * $from/$to must already be precise UTC instants (e.g. IST midnight
     * converted to UTC) — this method does no day-boundary math of its own,
     * deliberately, so timezone handling lives in exactly one place (the
     * caller). Re-deriving startOfDay()/endOfDay() here on an
     * already-UTC-timezoned Carbon instance would silently recompute the
     * boundary in UTC instead of Asia/Kolkata — the same off-by-5:30 bug
     * this app already hit once with Create Meeting (2026-07-29).
     *
     * @return Collection<int, array{at: Carbon, type: string, description: string, url: ?string}>
     */
    public function entries(User $user, Carbon $from, Carbon $to): Collection
    {
        $activityEntries = Activity::query()
            ->where('user_id', $user->id)
            ->whereIn('subject_type', self::TRACKED_SUBJECTS)
            ->whereBetween('created_at', [$from, $to])
            ->with('subject')
            ->get()
            ->map(fn (Activity $activity) => $this->describeActivity($activity));

        $callEntries = CallLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('called_at', [$from, $to])
            ->with('callable')
            ->get()
            ->map(fn (CallLog $call) => $this->describeCall($call));

        return $activityEntries->concat($callEntries)->sortByDesc('at')->values();
    }

    /**
     * Point-in-time snapshot of what's still open for this person — not
     * scoped to a date range, since "pending" means "as of right now."
     * Quotations/Invoices have no owner column of their own; attributed via
     * `Quotation::ownerId()` / `Invoice::ownerId()` (deal owner, falling
     * back to the customer's account owner — the same resolution the app
     * already uses to decide who to notify about them).
     *
     * @return Collection<int, array{type: string, description: string, url: string}>
     */
    public function pending(User $user): Collection
    {
        $tasks = Task::where('assignee_id', $user->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'Task',
                'description' => $task->title.($task->due_date ? ' — due '.$task->due_date->format('d M') : ''),
                'url' => route('tasks.show', $task),
            ]);

        $tickets = Ticket::where('assignee_id', $user->id)
            ->open()
            ->with('customer')
            ->latest()
            ->get()
            ->map(fn (Ticket $ticket) => [
                'type' => 'Ticket',
                'description' => $ticket->subject.' — '.($ticket->customer?->company_name ?? 'Client removed'),
                'url' => route('tickets.show', $ticket),
            ]);

        $leads = Lead::where('owner_id', $user->id)
            ->whereNotIn('status', [LeadStatus::Converted->value, LeadStatus::Lost->value])
            ->get()
            ->map(fn (Lead $lead) => [
                'type' => 'Lead',
                'description' => $lead->name.' ('.$lead->status->label().')',
                'url' => route('leads.show', $lead),
            ]);

        $deals = Deal::where('owner_id', $user->id)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->get()
            ->map(fn (Deal $deal) => [
                'type' => 'Deal',
                'description' => $deal->title.' ('.$deal->stage->label().')',
                'url' => route('deals.show', $deal),
            ]);

        $quotations = Quotation::where('status', QuotationStatus::Sent->value)
            ->get()
            ->filter(fn (Quotation $quotation) => $quotation->ownerId() === $user->id)
            ->map(fn (Quotation $quotation) => [
                'type' => 'Quotation',
                'description' => '#'.$quotation->number.' — awaiting client decision',
                'url' => route('quotations.show', $quotation),
            ]);

        $invoices = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->ownerId() === $user->id)
            ->map(fn (Invoice $invoice) => [
                'type' => 'Invoice',
                'description' => $invoice->invoice_number.' — '.Money::format($invoice->balance()).' outstanding',
                'url' => route('invoices.show', $invoice),
            ]);

        return $tasks->concat($tickets)->concat($leads)->concat($deals)->concat($quotations)->concat($invoices)->values();
    }

    private function describeActivity(Activity $activity): array
    {
        $type = class_basename($activity->subject_type);
        $subject = $activity->subject;
        $changes = $activity->changes ?? [];

        $description = match ($type) {
            'Deal' => $this->describeDeal($activity->event, $subject, $changes),
            'Quotation' => $this->describeQuotation($activity->event, $subject, $changes),
            'Invoice' => $this->describeInvoice($activity->event, $subject, $changes),
            'Payment' => $this->describePayment($activity->event, $subject, $changes),
            'Ticket' => $this->describeTicket($activity->event, $subject, $changes),
            'Task' => $this->describeTask($activity->event, $subject, $changes),
            'Lead' => $this->describeLead($activity->event, $subject, $changes),
            'Customer' => $this->describeCustomer($activity->event, $subject, $changes),
            'Project' => $this->describeProject($activity->event, $subject, $changes),
            default => ucfirst($activity->event)." {$type} #{$activity->subject_id}",
        };

        return [
            'at' => $activity->created_at,
            'type' => $type,
            'description' => $description,
            'url' => $subject ? $this->urlFor($type, $subject) : null,
        ];
    }

    private function describeCall(CallLog $call): array
    {
        $who = class_basename($call->callable_type ?? '');
        $name = $call->callable?->name ?? $call->callable?->company_name ?? "{$who} #{$call->callable_id}";

        return [
            'at' => $call->called_at,
            'type' => 'Call',
            'description' => ucfirst($call->direction->value)." call with {$name} — {$call->outcome->label()}"
                .($call->duration_minutes ? " ({$call->duration_minutes} min)" : ''),
            'url' => $call->callable ? $this->urlFor($who, $call->callable) : null,
        ];
    }

    private function describeDeal(string $event, ?Deal $deal, array $changes): string
    {
        if (! $deal) {
            return "{$event} a deal (removed)";
        }

        if ($event === 'updated' && isset($changes['stage'])) {
            return "Moved deal \"{$deal->title}\" to ".ucfirst($changes['stage']);
        }

        return $event === 'created' ? "Created deal \"{$deal->title}\"" : ucfirst($event)." deal \"{$deal->title}\"";
    }

    private function describeQuotation(string $event, ?Quotation $quotation, array $changes): string
    {
        if (! $quotation) {
            return "{$event} a quotation (removed)";
        }

        if ($event === 'updated' && isset($changes['status'])) {
            return "Quotation #{$quotation->number} marked ".ucfirst($changes['status']);
        }

        return $event === 'created' ? "Created quotation #{$quotation->number}" : ucfirst($event)." quotation #{$quotation->number}";
    }

    private function describeInvoice(string $event, ?Invoice $invoice, array $changes): string
    {
        if (! $invoice) {
            return "{$event} an invoice (removed)";
        }

        if ($event === 'updated' && isset($changes['status'])) {
            return "Invoice {$invoice->invoice_number} marked ".ucfirst(str_replace('_', ' ', $changes['status']));
        }

        return $event === 'created' ? "Raised invoice {$invoice->invoice_number}" : ucfirst($event)." invoice {$invoice->invoice_number}";
    }

    private function describePayment(string $event, ?Payment $payment, array $changes): string
    {
        if (! $payment) {
            return "{$event} a payment (removed)";
        }

        return $event === 'created'
            ? 'Recorded a payment of '.Money::format($payment->amount).' ('.$payment->mode->label().')'
            : ucfirst($event).' a payment of '.Money::format($payment->amount);
    }

    private function describeTicket(string $event, ?Ticket $ticket, array $changes): string
    {
        if (! $ticket) {
            return "{$event} a ticket (removed)";
        }

        if ($event === 'updated' && isset($changes['status'])) {
            return "Ticket \"{$ticket->subject}\" marked ".ucfirst(str_replace('_', ' ', $changes['status']));
        }

        return $event === 'created' ? "Logged ticket \"{$ticket->subject}\"" : ucfirst($event)." ticket \"{$ticket->subject}\"";
    }

    private function describeTask(string $event, ?Task $task, array $changes): string
    {
        if (! $task) {
            return "{$event} a task (removed)";
        }

        if ($event === 'updated' && isset($changes['status'])) {
            return "Task \"{$task->title}\" marked ".ucfirst(str_replace('_', ' ', $changes['status']));
        }

        return $event === 'created' ? "Created task \"{$task->title}\"" : ucfirst($event)." task \"{$task->title}\"";
    }

    private function describeLead(string $event, ?Lead $lead, array $changes): string
    {
        if (! $lead) {
            return "{$event} a lead (removed)";
        }

        if ($event === 'updated' && isset($changes['status'])) {
            return "Lead \"{$lead->name}\" marked ".ucfirst($changes['status']);
        }

        return $event === 'created' ? "Added lead \"{$lead->name}\"" : ucfirst($event)." lead \"{$lead->name}\"";
    }

    private function describeCustomer(string $event, ?Customer $customer, array $changes): string
    {
        if (! $customer) {
            return "{$event} a client (removed)";
        }

        return $event === 'created' ? "Added client \"{$customer->company_name}\"" : ucfirst($event)." client \"{$customer->company_name}\"";
    }

    private function describeProject(string $event, ?Project $project, array $changes): string
    {
        if (! $project) {
            return "{$event} a project (removed)";
        }

        return $event === 'created' ? "Started project \"{$project->name}\"" : ucfirst($event)." project \"{$project->name}\"";
    }

    private function urlFor(string $type, mixed $subject): ?string
    {
        return match ($type) {
            'Deal' => route('deals.show', $subject),
            'Quotation' => route('quotations.show', $subject),
            'Invoice' => route('invoices.show', $subject),
            'Payment' => $subject->invoice_id ? route('invoices.show', $subject->invoice_id) : null,
            'Ticket' => route('tickets.show', $subject),
            'Task' => route('tasks.show', $subject),
            'Lead' => route('leads.show', $subject),
            'Customer' => route('clients.show', $subject),
            'Project' => route('projects.show', $subject),
            default => null,
        };
    }
}
