<?php

namespace App\Livewire;

use App\Models\Contact;
use App\Models\Customer;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ContactsManager extends Component
{
    public Customer $customer;

    public bool $canManage = false;

    // Form state
    public ?int $editingId = null;

    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $designation = null;

    #[Validate('nullable|string|max:20')]
    public ?string $phone = null;

    #[Validate('nullable|email|max:255')]
    public ?string $email = null;

    public bool $is_primary = false;

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
    }

    public function newContact(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $contactId): void
    {
        $this->authorizeManage();
        $contact = $this->customer->contacts()->findOrFail($contactId);

        $this->editingId = $contact->id;
        $this->name = $contact->name;
        $this->designation = $contact->designation;
        $this->phone = $contact->phone;
        $this->email = $contact->email;
        $this->is_primary = $contact->is_primary;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $validated = $this->validate();

        $contact = $this->editingId
            ? $this->customer->contacts()->findOrFail($this->editingId)
            : new Contact(['customer_id' => $this->customer->id]);

        $contact->fill([
            'name' => $validated['name'],
            'designation' => $validated['designation'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
        ]);
        $contact->customer_id = $this->customer->id;
        $contact->save();

        // Enforce a single primary contact per client.
        if ($this->is_primary) {
            $contact->makePrimary();
        } elseif ($contact->is_primary) {
            $contact->forceFill(['is_primary' => false])->save();
        }

        $this->resetForm();
        $this->dispatch('contacts-updated');
    }

    public function makePrimary(int $contactId): void
    {
        $this->authorizeManage();
        $this->customer->contacts()->findOrFail($contactId)->makePrimary();
    }

    public function delete(int $contactId): void
    {
        $this->authorizeManage();
        $this->customer->contacts()->findOrFail($contactId)->delete();
        $this->dispatch('contacts-updated');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.contacts-manager', [
            'contacts' => $this->customer->contacts()
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'name', 'designation', 'phone', 'email', 'is_primary']);
        $this->resetValidation();
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('manage', $this->customer), 403);
    }
}
