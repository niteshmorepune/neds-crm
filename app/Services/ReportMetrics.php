<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\DailyReport;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations for the Employee Performance and Revenue reports. Kept separate
 * from the controller so the numbers are unit-testable. All periods are
 * inclusive [from 00:00, to 23:59:59].
 */
class ReportMetrics
{
    /**
     * One row per internal user for the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function employeePerformance(Carbon $from, Carbon $to): Collection
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // Cap at today so future days in a part-completed month don't dilute %.
        $effectiveTo = $to->copy()->min(now()->endOfDay());
        $workingDays = $this->countWorkingDays($from, $effectiveTo);

        return User::query()->orderBy('name')->get()->map(function (User $user) use ($from, $to, $fromDate, $toDate, $workingDays) {
            // Tasks COMPLETED in the period.
            $completedCount = Task::query()
                ->where('assignee_id', $user->id)
                ->whereBetween('completed_at', [$from, $to])
                ->count();

            // Tasks DUE in the period — denominator for on-time %, so overdue
            // tasks that were never finished count against the employee.
            $tasksDue = Task::query()
                ->where('assignee_id', $user->id)
                ->whereBetween('due_date', [$fromDate, $toDate])
                ->get(['due_date', 'completed_at']);

            $onTimeCount = $tasksDue->filter(fn (Task $t) => $t->completed_at !== null &&
                $t->completed_at->toDateString() <= $t->due_date->toDateString()
            )->count();

            $attendance = Attendance::query()
                ->where('user_id', $user->id)
                ->whereBetween('date', [$fromDate, $toDate])
                ->get(['status']);

            $presentEquivalent = $attendance->reduce(fn (float $c, Attendance $a) => $c + match ($a->status) {
                AttendanceStatus::Present => 1.0,
                AttendanceStatus::HalfDay => 0.5,
                default => 0.0,
            }, 0.0);

            return [
                'user' => $user->name,
                'role' => $user->role->label(),
                'tasks_completed' => $completedCount,
                'on_time_pct' => $tasksDue->count() > 0 ? (int) round($onTimeCount / $tasksDue->count() * 100) : null,
                'calls_made' => CallLog::query()->where('user_id', $user->id)->whereBetween('called_at', [$from, $to])->count(),
                'leads_converted' => Lead::query()->where('owner_id', $user->id)->whereBetween('converted_at', [$from, $to])->count(),
                // Divide by working days, not attendance record count.
                'attendance_pct' => ($attendance->count() > 0 && $workingDays > 0)
                    ? (int) round(min(100, $presentEquivalent / $workingDays * 100))
                    : null,
                'daily_reports' => DailyReport::query()->where('user_id', $user->id)->whereNotNull('submitted_at')->whereBetween('date', [$fromDate, $toDate])->count(),
            ];
        });
    }

    /**
     * Count Monday–Friday days between two dates, inclusive.
     */
    private function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $day = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($day->lte($end)) {
            if ($day->isWeekday()) {
                $days++;
            }
            $day->addDay();
        }

        return $days;
    }

    /**
     * Invoiced revenue for the period, split recurring vs one-time and grouped
     * by month, service line, and client. Draft and cancelled invoices excluded.
     */
    public function revenue(Carbon $from, Carbon $to): array
    {
        $invoices = Invoice::query()
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->with(['deal.service', 'customer'])
            ->get();

        $recurring = (int) $invoices->whereNotNull('recurring_invoice_id')->sum('total');
        $total = (int) $invoices->sum('total');

        $monthly = $invoices
            ->groupBy(fn (Invoice $i) => $i->issue_date->format('Y-m'))
            ->map(fn (Collection $group, string $month) => [
                'month' => $month,
                'recurring' => (int) $group->whereNotNull('recurring_invoice_id')->sum('total'),
                'one_time' => (int) $group->whereNull('recurring_invoice_id')->sum('total'),
                'total' => (int) $group->sum('total'),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $byService = $invoices
            ->groupBy(fn (Invoice $i) => $i->deal?->service?->name ?? 'Unspecified')
            ->map(fn (Collection $group, string $name) => ['name' => $name, 'total' => (int) $group->sum('total')])
            ->sortByDesc('total')
            ->values()
            ->all();

        $byClient = $invoices
            ->groupBy(fn (Invoice $i) => $i->customer?->company_name ?? 'Unknown')
            ->map(fn (Collection $group, string $name) => ['name' => $name, 'total' => (int) $group->sum('total')])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'recurring' => $recurring,
            'one_time' => $total - $recurring,
            'invoice_count' => $invoices->count(),
            'monthly' => $monthly,
            'by_service' => $byService,
            'by_client' => $byClient,
        ];
    }
}
