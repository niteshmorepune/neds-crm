<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ProjectStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Services\SlaCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends PortalController
{
    public function index(): View
    {
        return view('portal.tickets.index', [
            'tickets' => $this->customer()->tickets()->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        $projects = $this->customer()->projects()
            ->with('service')
            ->whereNotIn('status', [ProjectStatus::Completed->value])
            ->orderBy('name')
            ->get(['id', 'name', 'service_id']);

        return view('portal.tickets.create', [
            'priorities' => TicketPriority::cases(),
            'projects' => $projects,
        ]);
    }

    public function store(Request $request, SlaCalculator $sla): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
        ]);

        $priority = TicketPriority::from($data['priority']);

        // Resolve the project (scoped to this customer) and auto-assign to its lead.
        $project = null;
        $serviceId = null;
        $assigneeId = null;

        if (! empty($data['project_id'])) {
            $project = $this->customer()->projects()
                ->with(['assignees' => fn ($q) => $q->withPivot('role')])
                ->find($data['project_id']);

            if ($project) {
                $serviceId = $project->service_id;
                $lead = $project->assignees->firstWhere('pivot.role', 'lead')
                    ?? $project->assignees->first();
                $assigneeId = $lead?->id;
            }
        }

        $ticket = $this->customer()->tickets()->create([
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $priority->value,
            'status' => TicketStatus::Open->value,
            'sla_due_at' => $sla->dueAt(Carbon::now(), $priority->slaHours()),
            'service_id' => $serviceId,
            'assignee_id' => $assigneeId,
        ]);

        return redirect()->route('portal.tickets.show', $ticket->id)->with('status', 'Ticket raised.');
    }

    public function show(int $ticket): View
    {
        $ticket = $this->customer()->tickets()
            ->with(['replies' => fn ($q) => $q->where('is_internal', false), 'replies.author', 'replies.contact'])
            ->findOrFail($ticket);

        return view('portal.tickets.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $ticket = $this->customer()->tickets()->findOrFail($ticket);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->replies()->create([
            'contact_id' => auth('portal')->id(),
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        return back()->with('status', 'Reply sent.');
    }
}
