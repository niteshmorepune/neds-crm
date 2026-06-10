<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ClientNotes extends Component
{
    public Customer $customer;

    public bool $canManage = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
    }

    public function addNote(): void
    {
        abort_unless(auth()->user()?->can('manage', $this->customer), 403);

        $this->validate();

        // @mentions are kept as plain text per spec — no parsing.
        $this->customer->notes()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
        ]);

        $this->reset('body');
    }

    public function render()
    {
        return view('livewire.client-notes', [
            'notes' => $this->customer->notes()->with('author')->get(),
        ]);
    }
}
