<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
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
        return view('portal.tickets.create', ['priorities' => TicketPriority::cases()]);
    }

    public function store(Request $request, SlaCalculator $sla): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ]);

        $priority = TicketPriority::from($data['priority']);

        $ticket = $this->customer()->tickets()->create([
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $priority->value,
            'status' => TicketStatus::Open->value,
            'sla_due_at' => $sla->dueAt(Carbon::now(), $priority->slaHours()),
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
