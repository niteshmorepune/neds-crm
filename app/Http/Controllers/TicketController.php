<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Requests\TicketStoreRequest;
use App\Mail\TicketNotification;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\TicketEscalated;
use App\Services\SlaCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->with(['customer', 'assignee'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($request->boolean('breached'), fn ($q) => $q->open()->whereNotNull('sla_due_at')->where('sla_due_at', '<', now()))
            // Mirrors DashboardMetrics::supportStats()'s $atRisk definition
            // exactly ("next 4h or already breached") for the Support
            // dashboard's SLA-at-risk drill-down.
            ->when($request->boolean('at_risk'), fn ($q) => $q->open()->whereNotNull('sla_due_at')->where('sla_due_at', '<=', now()->addHours(4)))
            ->when($request->boolean('open'), fn ($q) => $q->open())
            ->when($request->boolean('escalated'), fn ($q) => $q->escalated())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('tickets.index', $this->formData() + [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority', 'mine', 'breached', 'at_risk', 'open', 'escalated']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.create', $this->formData() + ['ticket' => new Ticket(['priority' => TicketPriority::Normal->value])]);
    }

    public function store(TicketStoreRequest $request, SlaCalculator $sla): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $data = $request->validated();
        $priority = TicketPriority::from($data['priority']);

        $ticket = Ticket::create($data + [
            'created_by' => $request->user()->id,
            'status' => TicketStatus::Open->value,
            'sla_due_at' => $sla->dueAt(Carbon::now(), $priority->slaHours()),
        ]);

        $this->notifyCustomer($ticket, 'created');

        return redirect()->route('tickets.show', $ticket)->with('status', 'Ticket created.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['customer', 'service', 'assignee', 'replies.author', 'attachments.uploader']);

        return view('tickets.show', $this->formData() + [
            'ticket' => $ticket,
            'canManage' => $this->user()->can('update', $ticket),
            'drishtiUrl' => $this->drishtiContextUrl($ticket),
        ]);
    }

    private function drishtiContextUrl(Ticket $ticket): ?string
    {
        $clientId = $ticket->customer?->drishti_client_id;
        if (! $clientId) {
            return null;
        }

        $base = rtrim((string) config('services.drishti.base_url'), '/');
        $name = $ticket->service?->name ?? '';

        if (str_contains($name, 'SEO') || str_contains($name, 'GMB')) {
            return "{$base}/audit/{$clientId}";
        }

        if (str_contains($name, 'Social') || str_contains($name, 'Marketing')) {
            return "{$base}/optimize/{$clientId}";
        }

        return "{$base}/clients/{$clientId}";
    }

    public function update(Request $request, Ticket $ticket, SlaCalculator $sla): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'assignee_id' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ]);

        // Recompute the SLA deadline if priority changed.
        if ($data['priority'] !== $ticket->priority->value) {
            $ticket->sla_due_at = $sla->dueAt($ticket->created_at, TicketPriority::from($data['priority'])->slaHours());
        }

        $ticket->fill($data)->save();

        return back()->with('status', 'Ticket updated.');
    }

    public function resolve(Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $ticket->update(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()]);
        $this->notifyCustomer($ticket, 'resolved');

        return back()->with('status', 'Ticket resolved.');
    }

    public function escalate(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $ticket->update(['escalated_at' => now(), 'escalated_by' => $request->user()->id]);

        $recipients = User::withAnyRole(UserRole::Admin, UserRole::Manager)
            ->where('id', '!=', $request->user()->id)
            ->get();
        $recipients->each(fn (User $u) => $u->notify(new TicketEscalated($ticket)));

        return back()->with('status', 'Ticket escalated to managers.');
    }

    public function clearEscalation(Ticket $ticket): RedirectResponse
    {
        $this->authorize('manageEscalation', $ticket);

        $ticket->update(['escalated_at' => null, 'escalated_by' => null]);

        return back()->with('status', 'Escalation cleared.');
    }

    public function storeAttachment(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $file = $request->file('file');

        $ticket->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $file->store('attachments', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()
            ->with('status', 'Attachment uploaded.')
            ->with('attachment_uploaded', $file->getClientOriginalName())
            ->withFragment('attachments');
    }

    private function notifyCustomer(Ticket $ticket, string $kind, ?TicketReply $reply = null): void
    {
        if ($email = $ticket->customer->billingEmail()) {
            Mail::to($email)->send(new TicketNotification($ticket, $kind, $reply));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
        ];
    }

    private function user(): User
    {
        return auth()->user();
    }
}
