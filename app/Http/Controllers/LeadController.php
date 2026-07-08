<?php

namespace App\Http\Controllers;

use App\Actions\ConvertLead;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Requests\LeadStoreRequest;
use App\Http\Requests\LeadUpdateRequest;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $leads = Lead::query()
            ->visibleTo($request->user())
            ->with(['owner', 'service'])
            ->when($request->string('search')->trim()->value(), function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('leads.index', $this->formData() + [
            'leads' => $leads,
            'filters' => $request->only(['search', 'source', 'status', 'service_id', 'owner_id']),
        ]);
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

        $lead->load(['owner', 'service', 'convertedCustomer', 'convertedDeal']);

        return view('leads.show', [
            'lead' => $lead,
            'canManage' => $this->user()->can('update', $lead),
            'canConvert' => $this->user()->can('convert', $lead) && $lead->status !== LeadStatus::Converted,
        ]);
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
     * Convert the rupee-entered estimated value to integer paise.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $data['estimated_value'] = Money::toPaise($data['estimated_value'] ?? null);

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
