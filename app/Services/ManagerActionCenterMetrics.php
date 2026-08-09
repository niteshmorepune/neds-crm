<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Models\FollowUpReminder;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Manager panel doc's "Action Center" — one place aggregating signals that
 * already exist elsewhere in the app (Client Radar, Collections, Tickets,
 * Tasks, Contract & Renewal Dashboard, follow-up reminders) into severity
 * counts with drill-down links. Deliberately does NOT compute any new
 * business logic — every count here is either a direct call into an
 * existing service/scope, or a query copied verbatim from where that exact
 * definition already lives (e.g. the SLA-breach query mirrors
 * CheckTicketSla::handle() and the ?breached=1 filter TicketController
 * already exposes for it), so nothing here can define "overdue" or
 * "breached" differently from the page a manager lands on after clicking
 * through.
 *
 * "Escalated tickets" is the one signal here that isn't pure aggregation of
 * a pre-existing definition (see Escalation Management's own migration
 * docblock) — it's still included because, once tickets.escalated_at exists
 * and TicketController exposes ?escalated=1, this class's job is exactly to
 * surface that count with a drill-down link, same as every other signal.
 *
 * "Stagnant deals" was in the original ask but deliberately left out: no
 * reusable stagnation query exists anywhere in the app (only inline inside
 * the SendStagnationAlerts command, which mails owners directly rather than
 * exposing a queryable definition) — extracting one would be new business
 * logic, not aggregation, so it's flagged as a Tier 2 candidate instead of
 * built here.
 */
class ManagerActionCenterMetrics
{
    public function __construct(
        private readonly ClientRadarService $clientRadar,
        private readonly CollectionsMetrics $collections,
    ) {}

    /**
     * @return Collection<int, array{key: string, label: string, count: int, route: string, icon: string, color: string}>
     */
    public function signals(): Collection
    {
        return collect([
            [
                'key' => 'overdue_tasks',
                'label' => 'Overdue tasks',
                'count' => $this->overdueTaskCount(),
                'route' => route('tasks.index', ['overdue' => 1, 'type' => 'all']),
                'icon' => '⚠️',
                'color' => 'red',
            ],
            [
                'key' => 'at_risk_clients',
                'label' => 'Clients needing attention',
                'count' => $this->clientRadar->flaggedClients()->count(),
                'route' => route('client-radar.index'),
                'icon' => '📡',
                'color' => 'orange',
            ],
            [
                'key' => 'overdue_invoices',
                'label' => 'Overdue invoices',
                'count' => $this->overdueInvoiceCount(),
                'route' => route('invoices.index', ['status' => InvoiceStatus::Overdue->value]),
                'icon' => '💰',
                'color' => 'red',
            ],
            [
                'key' => 'sla_breaches',
                'label' => 'SLA-breached tickets',
                'count' => $this->slaBreachCount(),
                'route' => route('tickets.index', ['breached' => 1]),
                'icon' => '🚨',
                'color' => 'red',
            ],
            [
                'key' => 'escalated_tickets',
                'label' => 'Escalated tickets',
                'count' => Ticket::escalated()->count(),
                'route' => route('tickets.index', ['escalated' => 1]),
                'icon' => '🔺',
                'color' => 'red',
            ],
            [
                'key' => 'renewals_due',
                'label' => 'Renewals due in 30 days',
                'count' => RecurringInvoice::renewingWithin(30)->count(),
                'route' => route('contract-renewals.index'),
                'icon' => '🔄',
                'color' => 'amber',
            ],
        ]);
    }

    /**
     * No team-wide follow-up list page exists yet, so shown inline here
     * rather than as a drill-down link (matches this class's "aggregate,
     * don't build new surfaces" scope).
     *
     * @return Collection<int, FollowUpReminder>
     */
    public function pendingFollowUps(int $limit = 8): Collection
    {
        return FollowUpReminder::query()
            ->pending()
            ->with(['customer', 'user'])
            ->orderBy('remind_at')
            ->limit($limit)
            ->get();
    }

    public function pendingFollowUpCount(): int
    {
        return FollowUpReminder::pending()->count();
    }

    private function overdueTaskCount(): int
    {
        return Task::whereNotNull('assignee_id')
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->startOfDay())
            ->count();
    }

    private function overdueInvoiceCount(): int
    {
        return $this->collections->outstandingInvoicesQuery()
            ->where('status', InvoiceStatus::Overdue->value)
            ->count();
    }

    /**
     * Mirrors TicketController::index()'s ?breached=1 filter exactly (true
     * breach, not the "within 4h" at-risk definition DashboardMetrics uses).
     */
    private function slaBreachCount(): int
    {
        return Ticket::open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();
    }
}
