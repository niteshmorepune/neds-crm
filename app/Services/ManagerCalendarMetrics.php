<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Manager panel doc's "Manager Calendar" (Tier 3): "Meetings, task/project
 * deadlines and leave all have real due-dates today, just never in one
 * calendar view with filters." Pure aggregation of existing date fields —
 * no new business logic, same spirit as the Tier 2 aggregation-only items
 * (Revenue at Risk, Employee Performance Trends). Anticipated but
 * deliberately not built by MyMeetingInvitations (see its own docblock)
 * when that feature shipped.
 *
 * Company-wide, not scoped to the viewer — Task/Project policies already
 * treat viewAny as open for everyone with module access, and this page
 * itself is Admin/Manager only via menu.access:manager-calendar.
 */
class ManagerCalendarMetrics
{
    /**
     * @return Collection<int, array{type: string, date: string, time: ?string, title: string, subtitle: string, url: ?string}>
     */
    public function eventsBetween(Carbon $from, Carbon $to): Collection
    {
        return $this->meetingEvents($from, $to)
            ->merge($this->taskEvents($from, $to))
            ->merge($this->projectEvents($from, $to))
            ->merge($this->leaveEvents($from, $to))
            ->sortBy('date')
            ->values();
    }

    private function meetingEvents(Carbon $from, Carbon $to): Collection
    {
        // Wrapped in collect(...->all()) throughout this class: Eloquent
        // Collection::map() keeps the Eloquent Collection class even once
        // its items are plain arrays, and Eloquent\Collection::merge()
        // assumes Model items (calls ->getKey() on each) — fatal once
        // eventsBetween() merges these together. A plain base Collection
        // doesn't have that assumption.
        return collect(Meeting::whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('meetable')
            ->get()
            ->map(fn (Meeting $meeting) => [
                'type' => 'meeting',
                'date' => $meeting->occurred_at->toDateString(),
                'time' => $meeting->occurred_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('g:i A'),
                'title' => $meeting->title ?: 'Meeting',
                'subtitle' => $this->meetableLabel($meeting->meetable),
                'url' => $this->meetableUrl($meeting->meetable),
            ])->all());
    }

    private function taskEvents(Carbon $from, Carbon $to): Collection
    {
        return collect(Task::whereNotNull('due_date')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', TaskStatus::Done->value)
            ->with('assignee')
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'date' => $task->due_date->toDateString(),
                'time' => null,
                'title' => $task->title,
                'subtitle' => $task->assignee?->name ?? 'Unassigned',
                'url' => route('tasks.show', $task),
            ])->all());
    }

    private function projectEvents(Carbon $from, Carbon $to): Collection
    {
        return collect(Project::whereNotNull('end_date')
            ->whereBetween('end_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', ProjectStatus::Completed->value)
            ->with('owner')
            ->get()
            ->map(fn (Project $project) => [
                'type' => 'project',
                'date' => $project->end_date->toDateString(),
                'time' => null,
                'title' => $project->name.' (deadline)',
                'subtitle' => $project->owner?->name ?? 'Unassigned',
                'url' => route('projects.show', $project),
            ])->all());
    }

    /**
     * Expands each approved leave request into one event per business day
     * (reuses LeaveRequest::businessDays() — the same Mon-Sat expansion
     * already used to mark Attendance rows on approval, so a leave day
     * showing here always matches what Attendance actually records).
     */
    private function leaveEvents(Carbon $from, Carbon $to): Collection
    {
        $requests = LeaveRequest::where('status', LeaveRequestStatus::Approved->value)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->with('user')
            ->get();

        $events = collect();

        foreach ($requests as $request) {
            foreach ($request->businessDays() as $day) {
                $date = Carbon::parse($day);

                if ($date->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())) {
                    $events->push([
                        'type' => 'leave',
                        'date' => $date->toDateString(),
                        'time' => null,
                        'title' => $request->user?->name ?? 'Unknown',
                        'subtitle' => $request->type->label(),
                        'url' => route('leave-requests.approvals'),
                    ]);
                }
            }
        }

        return $events;
    }

    private function meetableLabel(Customer|Lead|null $meetable): string
    {
        if ($meetable instanceof Customer) {
            return $meetable->company_name;
        }

        return $meetable?->name ?? 'Unknown';
    }

    private function meetableUrl(Customer|Lead|null $meetable): ?string
    {
        return match (true) {
            $meetable instanceof Customer => route('clients.show', $meetable),
            $meetable instanceof Lead => route('leads.show', $meetable),
            default => null,
        };
    }
}
