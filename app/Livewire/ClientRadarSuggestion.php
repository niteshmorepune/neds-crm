<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

class ClientRadarSuggestion extends Component
{
    public int $customerId;

    /** @var array<string, array{label: string, detail: string}> */
    public array $flags;

    public bool $aiEnabled = false;

    /** Ephemeral AI suggestion shown in a dismissible panel (never persisted). */
    public ?string $suggestion = null;

    /**
     * @param  array<string, array{label: string, detail: string}>  $flags
     */
    public function mount(int $customerId, array $flags): void
    {
        $this->customerId = $customerId;
        $this->flags = $flags;
        $this->aiEnabled = Ai::enabled();
    }

    /**
     * Admin/Manager only — mirrors the client-radar.index page's own gating
     * (menu.access:client-radar), kept here too as defense-in-depth.
     */
    public function generate(AiAssistant $ai): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $customer = Customer::findOrFail($this->customerId);

        $this->suggestion = $ai->suggestClientAction($customer, $this->flags)
            ?? 'Could not generate a suggestion right now. Please try again.';
    }

    public function dismiss(): void
    {
        $this->suggestion = null;
    }

    public function render()
    {
        return view('livewire.client-radar-suggestion');
    }
}
