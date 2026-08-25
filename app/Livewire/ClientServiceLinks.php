<?php

namespace App\Livewire;

use App\Models\ClientServiceLink;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Per-client "Service Links" section on the Services tab — service-specific
 * URLs (Website URL/Hosting, Instagram/FB/LinkedIn, GBP link…). Mirrors
 * ImportantLinksManager's shape exactly, minus its global/company-wide mode
 * (this is always per-client) and its role-visibility scoping (view access
 * here just matches the Client Profile page itself).
 */
class ClientServiceLinks extends Component
{
    public Customer $customer;

    public bool $canManage = false;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $label = '';

    public string $url = '';

    public ?int $serviceId = null;

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
    }

    public function newLink(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $linkId): void
    {
        $this->authorizeManage();
        $link = $this->query()->findOrFail($linkId);

        $this->editingId = $link->id;
        $this->label = $link->label;
        $this->url = $link->url;
        $this->serviceId = $link->service_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = $this->validate($this->rules());
        $data = [
            'label' => $validated['label'],
            'url' => $validated['url'],
            'service_id' => $validated['serviceId'],
        ];

        if ($this->editingId) {
            $this->query()->findOrFail($this->editingId)->update($data);
        } else {
            ClientServiceLink::create($data + [
                'customer_id' => $this->customer->id,
                'created_by' => auth()->id(),
                'sort_order' => ((int) $this->query()->where('service_id', $data['service_id'])->max('sort_order')) + 1,
            ]);
        }

        $this->resetForm();
    }

    public function delete(int $linkId): void
    {
        $this->authorizeManage();
        $this->query()->findOrFail($linkId)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $links = $this->query()->with('service')->orderBy('service_id')->orderBy('sort_order')->orderBy('label')->get();

        return view('livewire.client-service-links', [
            'groupedLinks' => $links->groupBy(fn (ClientServiceLink $link) => $link->service?->name ?? 'Unassigned'),
            'services' => Service::active()->orderBy('sort_order')->get(),
        ]);
    }

    private function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'serviceId' => ['required', Rule::exists('services', 'id')],
        ];
    }

    private function query(): Builder
    {
        return $this->customer->clientServiceLinks()->getQuery();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'label', 'url', 'serviceId']);
        $this->resetValidation();
    }

    private function authorizeManage(): void
    {
        abort_unless((bool) auth()->user()?->can('manageServices', $this->customer), 403);
    }
}
