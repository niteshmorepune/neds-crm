<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * A single-question "ask about your account" box for the client portal,
 * grounded only in that client's own account data (AiAssistant::
 * answerPortalQuestion). The customer is always re-resolved from the
 * authenticated portal contact — never from a client-suppliable property —
 * so this can never be tricked into answering across customers.
 *
 * This is the one AI feature in the app a client triggers themselves
 * (everything else is staff-only), so it's the one that's rate-limited.
 */
class PortalAssistant extends Component
{
    use RatesAiDrafts;

    #[Validate('required|string|max:300')]
    public string $question = '';

    public ?string $answer = null;

    public bool $rateLimited = false;

    public bool $aiEnabled = false;

    public ?int $answerUsageId = null;

    public ?string $answerFeedback = null;

    public function mount(): void
    {
        $this->aiEnabled = Ai::enabled();
    }

    public function ask(AiAssistant $ai): void
    {
        abort_unless(Ai::enabled(), 403);

        $this->validate();
        $this->rateLimited = false;

        $contact = auth('portal')->user();
        $key = 'portal-assistant:'.$contact->id;
        $limit = (int) config('services.anthropic.portal_assistant_daily_limit');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $this->rateLimited = true;
            $this->answer = null;

            return;
        }

        RateLimiter::hit($key, 86400);

        $this->answerFeedback = null;
        $this->answer = $ai->answerPortalQuestion($contact->customer, $this->question)
            ?? "Sorry, I couldn't work that out from your account details. Please raise a ticket or contact your account manager.";
        $this->answerUsageId = $ai->lastUsageId;

        $this->question = '';
    }

    public function rateAnswer(string $direction): void
    {
        $this->recordAiFeedback($this->answerUsageId, $direction);
        $this->answerFeedback = $direction;
    }

    public function dismiss(): void
    {
        $this->answer = null;
        $this->rateLimited = false;
        $this->answerUsageId = null;
        $this->answerFeedback = null;
    }

    public function render()
    {
        return view('livewire.portal-assistant');
    }
}
