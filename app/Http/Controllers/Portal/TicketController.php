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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends PortalController
{
    /**
     * Allowed portal attachment types — kept deliberately narrower than the
     * internal (staff) attachment upload, since this is a public-facing form.
     */
    private const ATTACHMENT_RULES = ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv,zip'];

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
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => self::ATTACHMENT_RULES,
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

        foreach ($request->file('attachments', []) as $file) {
            $ticket->attachments()->create([
                'contact_id' => auth('portal')->id(),
                'disk' => 'local',
                'path' => $file->store('attachments', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return redirect()->route('portal.tickets.show', $ticket->id)->with('status', 'Ticket raised.');
    }

    public function show(int $ticket): View
    {
        $ticket = $this->customer()->tickets()
            ->with(['replies' => fn ($q) => $q->where('is_internal', false), 'replies.author', 'replies.contact', 'satisfactionRating', 'attachments'])
            ->findOrFail($ticket);

        return view('portal.tickets.show', ['ticket' => $ticket]);
    }

    public function downloadAttachment(int $ticket, int $attachment): StreamedResponse
    {
        $ticket = $this->customer()->tickets()->findOrFail($ticket);
        $attachment = $ticket->attachments()->findOrFail($attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
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

    public function rate(Request $request, int $ticket): RedirectResponse
    {
        $ticket = $this->customer()->tickets()->findOrFail($ticket);

        abort_if($ticket->status->isOpen(), 403);
        abort_if($ticket->satisfactionRating()->exists(), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->satisfactionRating()->create([
            'contact_id' => auth('portal')->id(),
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        return back()->with('status', 'Thanks for the feedback!');
    }
}
