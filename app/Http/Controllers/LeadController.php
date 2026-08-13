<?php

namespace App\Http\Controllers;

use App\Actions\ConvertLead;
use App\Actions\ReassignLead;
use App\Enums\LeadReassignmentReason;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Requests\LeadBulkReassignRequest;
use App\Http\Requests\LeadReassignRequest;
use App\Http\Requests\LeadStoreRequest;
use App\Http\Requests\LeadUpdateRequest;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        // Validated once, reused for both the query filter and the view's
        // "showing leads captured in <month>" banner — the banner must never
        // try to parse the raw, possibly-malformed request value.
        $month = $this->validMonth($request);

        $query = Lead::query()
            ->visibleTo($request->user())
            ->with(['owner', 'service', 'latestNote'])
            ->when($request->string('search')->trim()->value(), function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            // Mirrors DashboardMetrics::salesStats()'s 'followups_due' query
            // exactly, for the Sales dashboard's "Follow-ups due" drill-down
            // AND this page's own "Needs attention" strip (kept as the same
            // param rather than folded into `attention` below, so the
            // existing dashboard link never has to change).
            ->when($request->boolean('follow_up_due'), fn ($q) => $q->where('status', '!=', LeadStatus::Converted->value)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now()))
            ->when($request->input('attention') === 'due_today', fn ($q) => $q->whereIn('status', LeadStatus::openValues())
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', today())
                ->where('next_follow_up_at', '>', now()))
            ->when($request->input('attention') === 'hot_untouched', fn ($q) => $q->where('status', LeadStatus::New->value)
                ->whereNull('next_follow_up_at')
                ->where('ai_score', '>=', config('services.anthropic.hot_lead_threshold', 70)))
            ->when($month, function ($q) use ($month) {
                [$year, $monthNum] = explode('-', $month);
                $q->whereYear('created_at', $year)->whereMonth('created_at', $monthNum);
            });

        $sort = $request->input('sort') === 'newest' ? 'newest' : 'priority';

        if ($sort === 'newest') {
            $leads = $query->latest()->paginate(15)->withQueryString();
        } else {
            // Priority can't be expressed as portable SQL across this app's
            // MySQL-in-production/SQLite-in-tests split (Lead::priorityScore()
            // uses Carbon, not raw date functions) — fine at this app's scale
            // (low hundreds of leads), so sort/paginate in PHP instead.
            $sorted = $query->get()->sortByDesc(fn (Lead $lead) => $lead->priorityScore())->values();
            $page = max((int) $request->input('page', 1), 1);
            $leads = (new LengthAwarePaginator($sorted->forPage($page, 15), $sorted->count(), 15, $page, ['path' => $request->url()]))
                ->withQueryString();
        }

        $canBulkReassign = $request->user()->can('bulkReassign', Lead::class);
        $filterOwnerId = $request->filled('owner_id') ? $request->integer('owner_id') : null;

        return view('leads.index', $this->formData() + [
            'leads' => $leads,
            'filters' => $request->only(['search', 'source', 'status', 'service_id', 'owner_id', 'follow_up_due', 'attention', 'sort']) + ['month' => $month],
            'sort' => $sort,
            'statusCounts' => $this->statusCounts($request),
            'attentionCounts' => $this->attentionCounts($request),
            'canBulkReassign' => $canBulkReassign,
            'filterOwner' => $filterOwnerId ? User::find($filterOwnerId) : null,
            'bulkReassignOpenCount' => ($canBulkReassign && $filterOwnerId)
                ? Lead::where('owner_id', $filterOwnerId)->whereIn('status', LeadStatus::openValues())->count()
                : 0,
            'bulkReassignTargets' => $canBulkReassign
                ? User::where('is_active', true)->withAnyRole(UserRole::Sales, UserRole::Manager, UserRole::Admin)->orderBy('name')->get(['id', 'name'])
                : new Collection,
            'reassignReasons' => LeadReassignmentReason::cases(),
        ]);
    }

    /**
     * "Needs attention today" strip counts — deliberately ignores the ad-hoc
     * search/source/service filters, same philosophy as statusCounts(), so
     * the strip stays a stable prompt rather than echoing whatever the list
     * happens to be filtered to. Scoped to the viewer's own leads when their
     * PRIMARY role is Sales (this is "my queue, what do I work today"); left
     * unscoped for Telecaller (shared calling queue, not an owned pipeline —
     * same distinction LeadPolicy already draws) and for Admin/Manager
     * (oversight, not personal worklist).
     *
     * @return array{overdue: int, due_today: int, hot_untouched: int, scoped_to_own: bool}
     */
    private function attentionCounts(Request $request): array
    {
        $user = $request->user();
        $scopedToOwn = $user->role === UserRole::Sales;

        $base = fn () => Lead::query()->visibleTo($user)->when($scopedToOwn, fn ($q) => $q->where('owner_id', $user->id));

        return [
            'overdue' => (int) $base()->where('status', '!=', LeadStatus::Converted->value)
                ->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->count(),
            'due_today' => (int) $base()->whereIn('status', LeadStatus::openValues())
                ->whereNotNull('next_follow_up_at')->whereDate('next_follow_up_at', today())
                ->where('next_follow_up_at', '>', now())->count(),
            'hot_untouched' => (int) $base()->where('status', LeadStatus::New->value)
                ->whereNull('next_follow_up_at')
                ->where('ai_score', '>=', config('services.anthropic.hot_lead_threshold', 70))->count(),
            'scoped_to_own' => $scopedToOwn,
        ];
    }

    /**
     * Moves every OPEN lead owned by one user to another in a single action
     * — e.g. covering Kiran's leads with Mohit for the day. Admin/Manager
     * only (LeadPolicy::bulkReassign). Deliberately a plain one-time move,
     * not a temporary/auto-revert assignment: reassigning back later is the
     * same manual action in reverse, same as the single-lead Reassign button.
     * Reuses ReassignLead per lead so both paths log/notify identically.
     */
    public function bulkReassign(LeadBulkReassignRequest $request, ReassignLead $action): RedirectResponse
    {
        $from = User::findOrFail($request->validated('from_user_id'));
        $to = User::findOrFail($request->validated('to_user_id'));
        $reason = LeadReassignmentReason::from($request->validated('reason'));

        $openLeads = $from->leads()->whereIn('status', LeadStatus::openValues())->get();

        $openLeads->each(fn (Lead $lead) => $action->handle($lead, $to, $request->user(), $reason));

        // Preserve the owner_id filter so the admin lands back on the same
        // filtered view and can see it now shows zero open leads — the
        // best confirmation the action actually did something, especially
        // after this exact "looked like nothing happened" incident.
        return redirect()->route('leads.index', ['owner_id' => $from->id])
            ->with('status', "Reassigned {$openLeads->count()} open lead(s) from {$from->name} to {$to->name}.");
    }

    /**
     * Lead counts per status for the summary cards — scoped the same way as
     * the list (role visibility), but deliberately ignores the ad-hoc
     * search/source/service/owner filters so the cards stay a stable "whole
     * picture" rather than echoing whatever the list happens to be filtered to.
     *
     * @return array<string, int>
     */
    private function statusCounts(Request $request): array
    {
        $counts = Lead::query()
            ->visibleTo($request->user())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total' => (int) $counts->sum(),
            ...collect(LeadStatus::cases())->mapWithKeys(
                fn (LeadStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)]
            )->all(),
        ];
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('leads.create', $this->formData() + ['lead' => new Lead(['status' => LeadStatus::New->value])]);
    }

    public function store(LeadStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $lead = Lead::create($this->payload($request->validated()));

        return redirect()->route('leads.show', $lead)->with('status', 'Lead created.');
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load(['owner', 'service', 'convertedCustomer', 'convertedDeal', 'callLogs.user']);

        $canReassign = $this->user()->can('reassign', $lead);

        return view('leads.show', [
            'lead' => $lead,
            'canManage' => $this->user()->can('update', $lead),
            'canManageMeetings' => $this->user()->can('manageMeetings', $lead),
            'canConvert' => $this->user()->can('convert', $lead) && $lead->status !== LeadStatus::Converted,
            'canReassign' => $canReassign,
            'reassignTargets' => $canReassign ? $this->reassignTargets($this->user()) : new Collection,
            'reassignReasons' => LeadReassignmentReason::cases(),
        ]);
    }

    public function reassign(LeadReassignRequest $request, Lead $lead, ReassignLead $action): RedirectResponse
    {
        $to = User::findOrFail($request->validated('to_user_id'));
        $reason = LeadReassignmentReason::from($request->validated('reason'));

        $action->handle($lead, $to, $request->user(), $reason);

        return redirect()->route('leads.show', $lead)->with('status', "Lead reassigned to {$to->name}.");
    }

    /**
     * Admin/Manager can hand a lead to any active Sales/Manager/Admin user
     * (mirrors the Edit form's owner pool, active-only). A Sales user can
     * only hand off to another active Sales peer — never themselves.
     *
     * @return Collection<int, User>
     */
    private function reassignTargets(User $user): Collection
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return User::where('is_active', true)
                ->withAnyRole(UserRole::Sales, UserRole::Manager, UserRole::Admin)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return User::where('is_active', true)
            ->where('role', UserRole::Sales->value)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function edit(Lead $lead): View
    {
        $this->authorize('update', $lead);

        return view('leads.edit', $this->formData() + ['lead' => $lead]);
    }

    public function update(LeadUpdateRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $lead->update($this->payload($request->validated()));

        return redirect()->route('leads.show', $lead)->with('status', 'Lead updated.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()->route('leads.index')->with('status', 'Lead deleted.');
    }

    public function convert(Lead $lead, ConvertLead $converter): RedirectResponse
    {
        $this->authorize('convert', $lead);

        if ($lead->status === LeadStatus::Converted) {
            return back()->with('status', 'Lead is already converted.');
        }

        $deal = $converter->handle($lead);

        return redirect()->route('deals.show', $deal)
            ->with('status', 'Lead converted to a client and deal.');
    }

    public function quotation(Lead $lead, ConvertLead $converter): RedirectResponse
    {
        $this->authorize('view', $lead);

        if ($lead->status !== LeadStatus::Converted) {
            $this->authorize('convert', $lead);
            $deal = $converter->handle($lead);
            $lead->refresh();
        } else {
            $deal = $lead->convertedDeal;
        }

        return redirect()->route('quotations.create', array_filter([
            'customer_id' => $lead->converted_customer_id,
            'deal_id' => $deal?->id,
        ]));
    }

    /**
     * 'YYYY-MM' filter for a specific capture month — powers the Lead Source
     * Performance report's per-source drill-down links, so clicking a row
     * shows exactly the leads that row counted, not every lead of that
     * source ever. Returns null (no filter) for anything malformed rather
     * than erroring, same convention as the Recurring Invoices/Expenses
     * month filters.
     */
    private function validMonth(Request $request): ?string
    {
        $month = $request->string('month')->trim()->value();

        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;
    }

    /**
     * Convert the rupee-entered estimated value to integer paise.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $data['estimated_value'] = Money::toPaise($data['estimated_value'] ?? null);

        $data['next_follow_up_at'] = filled($data['next_follow_up_at'] ?? null)
            ? Carbon::createFromFormat('Y-m-d\TH:i', $data['next_follow_up_at'], config('app.display_timezone', 'Asia/Kolkata'))->utc()
            : null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'sources' => LeadSource::cases(),
            'statuses' => [LeadStatus::New, LeadStatus::Contacted, LeadStatus::Qualified, LeadStatus::Lost],
            'services' => Service::active()->orderBy('sort_order')->get(),
            'owners' => User::query()
                ->withAnyRole(UserRole::Sales, UserRole::Manager, UserRole::Admin)
                ->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function user(): User
    {
        return auth()->user();
    }
}
