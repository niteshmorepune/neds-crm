<?php

namespace App\Livewire;

use App\Enums\ClientAssetCategory;
use App\Enums\DeliverableStatus;
use App\Models\ClientRequirement;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Per-client "Requirements" tab — the client-level sibling of
 * ProjectDeliverables, for retainer services with no Project to hang a
 * checklist off of. Mirrors its UI shape (card + status badge + optional
 * file) plus the extra date fields + responsible-employee + service scope.
 * Attaching a received file creates a ClientAsset (so it also shows up in
 * the Assets & Documents library) rather than a second, separate attachment
 * — this is a deliberately SEPARATE action from the status dropdown, not
 * bundled into it, since a file can arrive before or after the status is
 * actually toggled.
 */
class ClientRequirements extends Component
{
    use WithFileUploads;

    public Customer $customer;

    public bool $canManage = false;

    public bool $showForm = false;

    public ?int $serviceId = null;

    public string $title = '';

    public string $instructions = '';

    public ?string $requestedDate = null;

    public ?string $dueDate = null;

    public ?int $responsibleUserId = null;

    public ?int $uploadingId = null;

    public $file = null;

    public ?string $fileCategory = null;

    public ?int $filterServiceId = null;

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
    }

    public function newRequirement(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = $this->validate($this->rules());

        $this->customer->clientRequirements()->create([
            'service_id' => $validated['serviceId'],
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?: null,
            'requested_date' => $validated['requestedDate'] ?: null,
            'due_date' => $validated['dueDate'] ?: null,
            'responsible_user_id' => $validated['responsibleUserId'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->resetForm();
    }

    public function updateStatus(int $requirementId, string $status): void
    {
        $this->authorizeManage();
        abort_unless(in_array($status, DeliverableStatus::values(), true), 422);

        $requirement = $this->customer->clientRequirements()->findOrFail($requirementId);
        $requirement->update([
            'status' => $status,
            'received_date' => $status === DeliverableStatus::Received->value
                ? ($requirement->received_date ?? now())
                : $requirement->received_date,
        ]);
    }

    public function startUpload(int $requirementId): void
    {
        $this->authorizeManage();
        $this->uploadingId = $requirementId;
        $this->file = null;
        $this->fileCategory = null;
    }

    public function uploadFile(): void
    {
        $this->authorizeManage();
        $requirement = $this->customer->clientRequirements()->findOrFail($this->uploadingId);

        $validated = $this->validate([
            'file' => ['required', 'file', 'max:10240'],
            'fileCategory' => ['nullable', Rule::enum(ClientAssetCategory::class)],
        ]);

        $path = $validated['file']->store('client-assets', 'local');

        $asset = $this->customer->clientAssets()->create([
            'service_id' => $requirement->service_id,
            'category' => $this->fileCategory ?: ClientAssetCategory::Other->value,
            'title' => $requirement->title,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $validated['file']->getClientOriginalName(),
            'mime_type' => $validated['file']->getClientMimeType(),
            'size' => $validated['file']->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $requirement->update(['client_asset_id' => $asset->id]);

        $this->uploadingId = null;
        $this->file = null;
        $this->fileCategory = null;
        $this->resetValidation();
    }

    public function cancelUpload(): void
    {
        $this->uploadingId = null;
        $this->file = null;
        $this->fileCategory = null;
        $this->resetValidation();
    }

    public function remove(int $requirementId): void
    {
        $this->authorizeManage();
        // The linked ClientAsset (if any) is NOT deleted here — it stays in
        // the Assets & Documents library independently of this checklist
        // item being removed.
        $this->customer->clientRequirements()->findOrFail($requirementId)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $requirements = $this->customer->clientRequirements()
            ->with(['service', 'responsible', 'clientAsset'])
            ->when($this->filterServiceId, fn ($q) => $q->where('service_id', $this->filterServiceId))
            ->orderBy('service_id')
            ->latest()
            ->get();

        return view('livewire.client-requirements', [
            'groupedRequirements' => $requirements->groupBy(fn (ClientRequirement $r) => $r->service?->name ?? 'Unassigned'),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => ClientAssetCategory::cases(),
        ]);
    }

    private function rules(): array
    {
        return [
            'serviceId' => ['required', Rule::exists('services', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'requestedDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:requestedDate'],
            'responsibleUserId' => ['nullable', Rule::exists('users', 'id')],
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'serviceId', 'title', 'instructions', 'requestedDate', 'dueDate', 'responsibleUserId']);
        $this->resetValidation();
    }

    private function authorizeManage(): void
    {
        abort_unless((bool) auth()->user()?->can('manageServices', $this->customer), 403);
    }
}
