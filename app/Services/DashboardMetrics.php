<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\TaskStatus;
use App\Enums\TicketPriority;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;

/**
 * Read-only aggregates for the dashboards. Each method returns plain arrays
 * ready for a Blade view. "% from last month" compares the current total to the
 * total that existed at the end of last month (rows created on/before that
 * date), so it reads as growth rather than churn.
 */
class DashboardMetrics
{
    /** Row 1 stat cards for the admin/manager dashboard. */
    public function adminStats(): array
    {
        $cutoff = now()->subMonthNoOverflow()->endOfMonth();

        $clients = Customer::query();
        $totalClients = (int) $clients->clone()->count();
        $activeClients = (int) $clients->clone()->where('status', CustomerStatus::Active->value)->count();
        $inactiveClients = (int) $clients->clone()->where('status', CustomerStatus::Inactive->value)->count();
        $totalTasks = (int) Task::query()->count();

        return [
            'clients_total' => $this->card($totalClients, Customer::where('created_at', '<=', $cutoff)->count()),
            'clients_active' => $this->card($activeClients, Customer::where('status', CustomerStatus::Active->value)->where('created_at', '<=', $cutoff)->count()),
            'clients_inactive' => $this->card($inactiveClients, Customer::where('status', CustomerStatus::Inactive->value)->where('created_at', '<=', $cutoff)->count()),
            'tasks_total' => $this->card($totalTasks, Task::where('created_at', '<=', $cutoff)->count()),
        ];
    }

    /** Projects grouped by service line, for the Services Overview donut. */
    public function servicesOverview(): array
    {
        $counts = Project::query()
            ->selectRaw('service_id, COUNT(*) as aggregate')
            ->groupBy('service_id')
            ->pluck('aggregate', 'service_id');

        $total = (int) $counts->sum();
        $names = Service::whereIn('id', $counts->keys())->pluck('name', 'id');

        $segments = $counts->map(fn ($count, $serviceId) => [
            'name' => $names[$serviceId] ?? 'Unspecified',
            'count' => (int) $count,
            'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
        ])->sortByDesc('count')->values()->all();

        return ['total' => $total, 'segments' => $segments];
    }

    /** Task Summary card: assigned / pending / overdue / completed. */
    public function taskSummary(): array
    {
        $today = now()->startOfDay();

        return [
            'assigned' => (int) Task::query()->count(),
            'pending' => (int) Task::query()->where('status', '!=', TaskStatus::Done->value)->count(),
            'overdue' => (int) Task::query()
                ->where('status', '!=', TaskStatus::Done->value)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count(),
            'completed' => (int) Task::query()->where('status', TaskStatus::Done->value)->count(),
        ];
    }

    /** Sales rep dashboard: pipeline by stage, follow-ups due, won this month. */
    public function salesStats(User $user): array
    {
        $pipeline = Deal::query()
            ->visibleTo($user)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->selectRaw('stage, COUNT(*) as deals, COALESCE(SUM(value),0) as value')
            ->groupBy('stage')
            ->get()
            ->map(fn ($row) => [
                // `stage` is cast to the enum even on a raw select.
                'stage' => ($row->stage instanceof DealStage ? $row->stage : DealStage::from($row->stage))->label(),
                'deals' => (int) $row->deals,
                'value' => (int) $row->value,
            ])->all();

        return [
            'pipeline' => $pipeline,
            'followups_due' => (int) Lead::query()->visibleTo($user)
                ->where('status', '!=', LeadStatus::Converted->value)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now())
                ->count(),
            'won_this_month_value' => (int) Deal::query()->visibleTo($user)
                ->where('stage', DealStage::Won->value)
                ->where('updated_at', '>=', now()->startOfMonth())
                ->sum('value'),
        ];
    }

    /** Accounts dashboard: receivables, collections, overdue. */
    public function accountsStats(): array
    {
        $outstanding = (int) Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Draft->value])
            ->selectRaw('COALESCE(SUM(total - amount_paid),0) as due')
            ->value('due');

        $collected = (int) Payment::query()
            ->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        return [
            'outstanding' => max(0, $outstanding),
            'collected_this_month' => $collected,
            'overdue_count' => (int) Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count(),
        ];
    }

    /** Support dashboard: open tickets by priority + SLA at-risk. */
    public function supportStats(User $user): array
    {
        $byPriority = Ticket::query()->visibleTo($user)->open()
            ->selectRaw('priority, COUNT(*) as aggregate')
            ->groupBy('priority')
            ->pluck('aggregate', 'priority');

        $priorities = collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [
            $p->label() => (int) ($byPriority[$p->value] ?? 0),
        ])->all();

        $atRisk = (int) Ticket::query()->visibleTo($user)->open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now()->addHours(4))
            ->count();

        return [
            'open_by_priority' => $priorities,
            'open_total' => (int) array_sum($priorities),
            'sla_at_risk' => $atRisk,
        ];
    }

    /**
     * Build a stat card payload: value + signed % change vs last month.
     */
    private function card(int $current, int $previous): array
    {
        return [
            'value' => $current,
            'change' => $this->pctChange($current, $previous),
        ];
    }

    private function pctChange(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
