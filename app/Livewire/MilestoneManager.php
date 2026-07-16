<?php

namespace App\Livewire;

use App\Actions\GenerateMilestoneInvoice;
use App\Enums\MilestoneStatus;
use App\Models\Quotation;
use Livewire\Component;

class MilestoneManager extends Component
{
    public Quotation $quotation;

    public bool $canManage = false;

    public string $title = '';

    public string $percentage = '';

    public ?string $due_date = null;

    public function mount(Quotation $quotation, bool $canManage = false): void
    {
        $this->quotation = $quotation;
        $this->canManage = $canManage;
    }

    public function addMilestone(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
        ]);

        $newTotal = $this->usedPercentage() + (float) $validated['percentage'];
        if ($newTotal > 100.0) {
            $this->addError('percentage', 'Milestones cannot exceed 100% (used '.$this->usedPercentage().'%).');

            return;
        }

        $this->quotation->milestones()->create([
            'title' => $validated['title'],
            'percentage' => $validated['percentage'],
            'amount' => (int) round((int) $this->quotation->subtotal * (float) $validated['percentage'] / 100),
            'due_date' => $validated['due_date'] ?: null,
            'sort_order' => $this->quotation->milestones()->count(),
        ]);

        $this->reset(['title', 'percentage', 'due_date']);
    }

    public function removeMilestone(int $milestoneId): void
    {
        $this->authorizeManage();
        $milestone = $this->quotation->milestones()->findOrFail($milestoneId);
        abort_if($milestone->isBilled(), 403, 'A billed milestone cannot be removed.');
        $milestone->delete();
    }

    /**
     * Team-set work-progress status, independent of billing (isBilled()).
     * Marking a milestone Done is the manual signal that flips readyToInvoice().
     */
    public function updateStatus(int $milestoneId, string $status): void
    {
        $this->authorizeManage();
        abort_unless(in_array($status, MilestoneStatus::values(), true), 422);

        $milestone = $this->quotation->milestones()->findOrFail($milestoneId);
        $milestone->update(['status' => $status]);
    }

    public function generate(int $milestoneId, GenerateMilestoneInvoice $action): void
    {
        $this->authorizeManage();
        $milestone = $this->quotation->milestones()->findOrFail($milestoneId);

        if (! $milestone->isBilled()) {
            $action->handle($milestone);
        }
    }

    public function usedPercentage(): float
    {
        return (float) $this->quotation->milestones()->sum('percentage');
    }

    public function render()
    {
        $milestones = $this->quotation->milestones()->with(['invoice', 'tasks'])->get();
        $billed = $milestones->filter->isBilled()->sum(fn ($m) => (int) $m->invoice?->total);
        $collected = $milestones->filter->isBilled()->sum(fn ($m) => (int) $m->invoice?->amount_paid);

        return view('livewire.milestone-manager', [
            'milestones' => $milestones,
            'billed' => $billed,
            'collected' => $collected,
            'remaining' => max(0, (int) $this->quotation->total - $billed),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage, 403);
    }
}
