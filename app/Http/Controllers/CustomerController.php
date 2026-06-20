<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Http\Requests\CustomerStoreRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Models\Customer;
use App\Models\Invoice;
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
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', [
            'customers' => $customers,
            'owners' => $this->assignableOwners(),
            'statuses' => CustomerStatus::cases(),
            'statusFilter' => $statusFilter,
            'filters' => $request->only(['search', 'status', 'owner_id']),
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
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'callLogs.user',
            'deals.owner',
            'tickets.assignee',
            'projects.service',
            'projects.owner',
            'projects.assignees',
            'recurringInvoices.service',
            'recurringInvoices.items',
        ]);

        if ($this->user()->can('viewAny', Invoice::class)) {
            $client->load('invoices');
        }

        return view('clients.show', [
            'client' => $client,
            'canManage' => $this->user()->can('manage', $client),
            'canViewInvoices' => $this->user()->can('viewAny', Invoice::class),
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
            ->whereIn('role', [
                UserRole::Sales->value,
                UserRole::Manager->value,
                UserRole::Admin->value,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    private function user(): User
    {
        return auth()->user();
    }
}
