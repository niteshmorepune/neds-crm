<?php

namespace App\Livewire;

use App\Enums\ClientAssetCategory;
use App\Models\ClientAsset;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Per-client "Assets" tab — a categorized file library, mirroring
 * TeamResourceLibrary's shape (the company-wide equivalent). Unlike every
 * other attachment flow in this app, uploading over an existing asset
 * ("Replace/Upload New Version") keeps the old file downloadable via
 * ClientAsset::replaceFile() rather than silently overwriting it.
 */
class ClientAssets extends Component
{
    use WithFileUploads;

    public Customer $customer;

    public bool $canManage = false;

    public bool $showForm = false;

    public string $title = '';

    public ?string $category = null;

    public ?int $serviceId = null;

    public $file = null;

    public ?int $replacingId = null;

    public $replacementFile = null;

    public ?string $filterCategory = null;

    /** @var array<int, int> Asset ids whose version history is currently expanded. */
    public array $expanded = [];

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
    }

    public function newAsset(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ClientAssetCategory::class)],
            'serviceId' => ['nullable', Rule::exists('services', 'id')],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $path = $validated['file']->store('client-assets', 'local');

        $this->customer->clientAssets()->create([
            'service_id' => $validated['serviceId'] ?: null,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $validated['file']->getClientOriginalName(),
            'mime_type' => $validated['file']->getClientMimeType(),
            'size' => $validated['file']->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->resetForm();
    }

    public function startReplace(int $assetId): void
    {
        $this->authorizeManage();
        $this->replacingId = $assetId;
        $this->replacementFile = null;
    }

    public function replace(): void
    {
        $this->authorizeManage();
        $asset = $this->customer->clientAssets()->findOrFail($this->replacingId);

        $validated = $this->validate([
            'replacementFile' => ['required', 'file', 'max:20480'],
        ]);

        $asset->replaceFile($validated['replacementFile'], auth()->id());

        $this->replacingId = null;
        $this->replacementFile = null;
        $this->resetValidation();
    }

    public function cancelReplace(): void
    {
        $this->replacingId = null;
        $this->replacementFile = null;
        $this->resetValidation();
    }

    public function toggleVersions(int $assetId): void
    {
        if (in_array($assetId, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$assetId]));
        } else {
            $this->expanded[] = $assetId;
        }
    }

    public function delete(int $assetId): void
    {
        $this->authorizeManage();
        $this->customer->clientAssets()->findOrFail($assetId)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $assets = $this->customer->clientAssets()
            ->with(['service', 'uploader', 'versions.uploader'])
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->orderBy('title')
            ->get();

        return view('livewire.client-assets', [
            'groupedAssets' => $assets->groupBy(fn (ClientAsset $a) => $a->category->value)
                ->sortBy(fn ($group, $key) => ClientAssetCategory::from($key)->label()),
            'categories' => ClientAssetCategory::cases(),
            'services' => Service::active()->orderBy('sort_order')->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'title', 'category', 'serviceId', 'file']);
        $this->resetValidation();
    }

    private function authorizeManage(): void
    {
        abort_unless((bool) auth()->user()?->can('manageServices', $this->customer), 403);
    }
}
