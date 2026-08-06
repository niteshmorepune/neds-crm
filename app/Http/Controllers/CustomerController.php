<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Http\Requests\CustomerStoreRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        // Default to Active only; pass status=all to see everyone.
        $statusFilter = $request->input('status', CustomerStatus::Active->value);
        $sort = in_array($request->input('sort'), ['name', 'oldest', 'location'], true) ? $request->input('sort') : 'newest';

        $customers = Customer::query()
            ->visibleTo($request->user())
            ->with(['owner', 'primaryContact'])
            ->withCount('contacts')
            ->when($request->string('search')->trim()->value(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('gstin', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('referring_partner_id'), fn ($q) => $q->where('referring_partner_id', $request->integer('referring_partner_id')))
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')->value()))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')->value()))
            ->when($sort === 'name', fn ($q) => $q->orderBy('company_name'))
            ->when($sort === 'oldest', fn ($q) => $q->oldest())
            ->when($sort === 'location', fn ($q) => $q->orderByRaw('state IS NULL, state')->orderByRaw('city IS NULL, city')->orderBy('company_name'))
            ->when($sort === 'newest', fn ($q) => $q->latest())
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', [
            'customers' => $customers,
            'owners' => $this->assignableOwners(),
            'statuses' => CustomerStatus::cases(),
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'partners' => Partner::orderBy('name')->get(),
            // Only states/cities actually present on a client, not the full
            // ~36-entry India state list — a company mostly serving
            // Maharashtra clients doesn't need every state in the dropdown.
            'states' => Customer::query()->visibleTo($request->user())->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
            'cities' => Customer::query()->visibleTo($request->user())->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'filters' => $request->only(['search', 'status', 'owner_id', 'referring_partner_id', 'state', 'city', 'sort']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('clients.create', [
            'customer' => new Customer(['status' => CustomerStatus::Active->value]),
            'states' => config('india.states'),
            'owners' => $this->assignableOwners(),
            'statuses' => CustomerStatus::cases(),
            'partners' => Partner::orderBy('name')->get(),
        ]);
    }

    public function store(CustomerStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create($this->payload($request->validated()));

        return redirect()
            ->route('clients.show', $customer)
            ->with('status', 'Client created.');
    }

    public function show(Customer $client): View
    {
        $this->authorize('view', $client);

        $client->load([
            'owner',
            'referringPartner',
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'callLogs.user',
            'deals.owner',
            'tickets.assignee',
            'tickets.satisfactionRating',
            'projects.service',
            'projects.owner',
            'projects.assignees',
            'recurringInvoices.service',
            'recurringInvoices.items',
        ]);

        $canViewInvoices = $this->user()->can('viewAny', Invoice::class);

        if ($canViewInvoices) {
            $client->load('invoices');
            $client->load('recurringInvoices.invoices');
        }

        $client->loadCount(['notes', 'links']);

        // Mirrors the tab keys in clients/show.blade.php exactly. "services"
        // matches what _services_tab.blade.php actually lists (recurring
        // templates minus orphans, plus projects), not a raw relation count.
        $tabCounts = [
            'services' => $client->nonOrphanedRecurringInvoices()->count() + $client->projects->count(),
            'notes' => $client->notes_count,
            'calls' => $client->callLogs->count(),
            'deals' => $client->deals->count(),
            'tickets' => $client->tickets->count(),
            'links' => $client->links_count,
        ];

        if ($canViewInvoices) {
            $tabCounts['invoices'] = $client->invoices->count();
        }

        return view('clients.show', [
            'client' => $client,
            'canManage' => $this->user()->can('manage', $client),
            'canManageMeetings' => $this->user()->can('manageMeetings', $client),
            'canManageLinks' => $this->user()->can('manageLinks', $client),
            'canViewInvoices' => $canViewInvoices,
            'tabCounts' => $tabCounts,
        ]);
    }

    public function edit(Customer $client): View
    {
        $this->authorize('update', $client);

        return view('clients.edit', [
            'customer' => $client,
            'states' => config('india.states'),
            'owners' => $this->assignableOwners(),
            'statuses' => CustomerStatus::cases(),
            'partners' => Partner::orderBy('name')->get(),
        ]);
    }

    public function update(CustomerUpdateRequest $request, Customer $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($this->payload($request->validated()));

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Client updated.');
    }

    public function destroy(Customer $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client and all related records deleted.');
    }

    /**
     * Add the derived state name from the chosen GST state code.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $isOverseas = ! empty($data['country']) && strtolower(trim($data['country'])) !== 'india';

        if ($isOverseas) {
            // Overseas clients have no GST state code or GSTIN.
            $data['state_code'] = null;
            $data['state'] = null;
            $data['gstin'] = null;
        } else {
            $data['state'] = ! empty($data['state_code'])
                ? config("india.states.{$data['state_code']}")
                : null;
        }

        return $data;
    }

    /**
     * Users who can be set as a client owner (sales reps + managers/admins).
     */
    private function assignableOwners()
    {
        return User::query()
            ->withAnyRole(UserRole::Sales, UserRole::Manager, UserRole::Admin)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    private function user(): User
    {
        return auth()->user();
    }
}
