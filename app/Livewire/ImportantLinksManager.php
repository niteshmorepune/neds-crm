<?php

namespace App\Livewire;

use App\Enums\LinkDepartment;
use App\Enums\LinkPurpose;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ImportantLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Handles both the standalone "Important Links" (company-wide, Admin/Manager
 * managed) page and the per-client "Links" tab — same shape, so one
 * component covers both rather than two near-duplicates. Global mode:
 * $customer is null. Per-client mode: $customer is set and $canManage
 * mirrors the prop CustomerController::show() already computes (re-checked
 * at action time regardless, same defense-in-depth as
 * ContactsManager::authorizeManage()).
 */
class ImportantLinksManager extends Component
{
    public ?Customer $customer = null;

    public bool $canManage = false;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $label = '';

    public string $url = '';

    public ?string $department = null;

    public ?string $purpose = null;

    /** Display-only filters — narrow the rendered list, never the mutation lookups in query(). */
    public ?string $filterDepartment = null;

    public ?string $filterPurpose = null;

    public function mount(?Customer $customer = null, bool $canManage = false): void
    {
        // Livewire's mount() parameter resolution container-resolves an
        // un-passed typed-class param (an empty, unsaved Customer) rather
        // than leaving it PHP null, so ?->exists (not truthiness) is the
        // real "was a customer actually passed" check — everything below
        // (query(), authorizeManage(), the view's @if ($customer)) relies
        // on $this->customer being true null in global mode.
        $this->customer = $customer?->exists ? $customer : null;
        $this->canManage = $this->customer
            ? $canManage
            : (bool) auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager);
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
        $this->department = $link->department?->value;
        $this->purpose = $link->purpose?->value;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = $this->validate($this->rules());

        if ($this->editingId) {
            $this->query()->findOrFail($this->editingId)->update($validated);
        } else {
            // ->getQuery() in query() returns a plain Builder, so create()
            // won't auto-fill the foreign key the way HasMany::create()
            // would — set customer_id explicitly instead.
            ImportantLink::create($validated + [
                'customer_id' => $this->customer?->id,
                'created_by' => auth()->id(),
                'sort_order' => ((int) $this->query()->max('sort_order')) + 1,
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
        $links = $this->query()
            ->when($this->filterDepartment, fn ($q) => $q->where('department', $this->filterDepartment))
            ->when($this->filterPurpose, fn ($q) => $q->where('purpose', $this->filterPurpose))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('livewire.important-links-manager', [
            // Grouped by department for display, alphabetized by label with
            // "Uncategorized" (null) always last so real categories are what
            // a viewer sees up top. groupBy() preserves each group's
            // existing sort_order/label ordering from the query above.
            'groupedLinks' => $links
                ->groupBy(fn (ImportantLink $link) => $link->department?->value ?? '')
                ->sortBy(fn ($group, $key) => $key === '' ? 'zzzz' : LinkDepartment::from($key)->label()),
            'departments' => LinkDepartment::cases(),
            'purposes' => LinkPurpose::cases(),
        ]);
    }

    private function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'department' => ['nullable', Rule::enum(LinkDepartment::class)],
            'purpose' => ['nullable', Rule::enum(LinkPurpose::class)],
        ];
    }

    private function query(): Builder
    {
        return $this->customer
            ? $this->customer->links()->getQuery()
            : ImportantLink::query()->global();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'label', 'url', 'department', 'purpose']);
        $this->resetValidation();
    }

    private function authorizeManage(): void
    {
        $allowed = $this->customer
            ? (bool) auth()->user()?->can('manageLinks', $this->customer)
            : (bool) auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager);

        abort_unless($allowed, 403);
    }
}
