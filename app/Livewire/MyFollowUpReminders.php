<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\FollowUpReminder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Dashboard widget: set and track personal follow-up reminders against a
 * client — a call to make, a report to share, a quotation to send, a deal to
 * chase. Notified when due by App\Console\Commands\SendDashboardFollowUpReminders.
 */
class MyFollowUpReminders extends Component
{
    public bool $showForm = false;

    #[Validate('nullable|exists:customers,id')]
    public ?int $customer_id = null;

    #[Validate('required|string')]
    public string $remind_at = '';

    #[Validate('required|string|max:255')]
    public string $next_action = '';

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
    }

    public function save(): void
    {
        $validated = $this->validate();

        FollowUpReminder::create([
            'user_id' => auth()->id(),
            'customer_id' => $validated['customer_id'],
            'remind_at' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['remind_at'], config('app.display_timezone', 'Asia/Kolkata'))->utc(),
            'next_action' => $validated['next_action'],
        ]);

        $this->reset(['customer_id', 'remind_at', 'next_action', 'showForm']);
        $this->resetValidation();
    }

    public function markDone(int $reminderId): void
    {
        $reminder = FollowUpReminder::findOrFail($reminderId);
        abort_unless($reminder->user_id === auth()->id(), 403);

        $reminder->update(['completed_at' => now()]);
    }

    public function render()
    {
        $reminders = FollowUpReminder::query()
            ->forUser(auth()->user())
            ->pending()
            ->with('customer')
            ->orderBy('remind_at')
            ->get();

        return view('livewire.my-follow-up-reminders', [
            'reminders' => $reminders,
            'customers' => Customer::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }
}
