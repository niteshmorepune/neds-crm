<?php

namespace App\Livewire;

use App\Enums\TeamResourceCategory;
use App\Enums\UserRole;
use App\Models\TeamResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The "Files" tab of the Resources page — company-wide internal file
 * library. Mirrors ImportantLinksManager's shape (form/list/filter in one
 * component). Upload/edit/delete is Admin/Manager only (TeamResourcePolicy);
 * everyone else gets a read-only list already filtered to what their role
 * can see (HasRoleVisibility on the model).
 */
class TeamResourceLibrary extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $title = '';

    public string $description = '';

    public ?string $category = null;

    public $file = null;

    /** @var array<int, string> */
    public array $visibleRoles = [];

    public ?string $filterCategory = null;

    public function newResource(): void
    {
        $this->authorize('create', TeamResource::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $resourceId): void
    {
        $resource = TeamResource::findOrFail($resourceId);
        $this->authorize('update', $resource);

        $this->editingId = $resource->id;
        $this->title = $resource->title;
        $this->description = (string) $resource->description;
        $this->category = $resource->category?->value;
        $this->visibleRoles = $resource->visibleRoles->map(fn ($vr) => $vr->role->value)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        if ($this->editingId) {
            $resource = TeamResource::findOrFail($this->editingId);
            $this->authorize('update', $resource);

            $validated = $this->validate($this->metadataRules());
            $resource->update($validated);
        } else {
            $this->authorize('create', TeamResource::class);

            $validated = $this->validate($this->metadataRules() + [
                'file' => ['required', 'file', 'max:20480'],
            ]);

            $path = $this->file->store('team-resources', 'local');

            $resource = TeamResource::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'disk' => 'local',
                'path' => $path,
                'original_name' => $this->file->getClientOriginalName(),
                'mime_type' => $this->file->getClientMimeType(),
                'size' => $this->file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }

        $resource->syncVisibleRoles($this->visibleRoles);
        $this->resetForm();
    }

    public function delete(int $resourceId): void
    {
        $resource = TeamResource::findOrFail($resourceId);
        $this->authorize('delete', $resource);
        $resource->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $resources = TeamResource::query()
            ->visibleTo(Auth::user())
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->orderBy('title')
            ->get();

        return view('livewire.team-resource-library', [
            // Grouped by category, "Uncategorized" last — same shape as
            // ImportantLinksManager's groupedLinks.
            'groupedResources' => $resources
                ->groupBy(fn (TeamResource $r) => $r->category?->value ?? '')
                ->sortBy(fn ($group, $key) => $key === '' ? 'zzzz' : TeamResourceCategory::from($key)->label()),
            'categories' => TeamResourceCategory::cases(),
            'assignableRoles' => collect(UserRole::cases())->reject(fn (UserRole $r) => $r === UserRole::Admin)->values(),
            'canManage' => (bool) Auth::user()?->can('create', TeamResource::class),
        ]);
    }

    private function metadataRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', Rule::enum(TeamResourceCategory::class)],
            'visibleRoles' => ['array'],
            'visibleRoles.*' => [Rule::enum(UserRole::class)],
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'title', 'description', 'category', 'file', 'visibleRoles']);
        $this->resetValidation();
    }
}
