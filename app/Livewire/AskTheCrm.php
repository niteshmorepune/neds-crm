<?php

namespace App\Livewire;

use App\Enums\CrmQueryType;
use App\Enums\UserRole;
use App\Livewire\Concerns\RatesAiDrafts;
use App\Services\AiAssistant;
use App\Services\CrmQueryCatalog;
use App\Support\Ai;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * "Ask the CRM" — a single-question box that maps a free-text business
 * question to one of a bounded set of report types (CrmQueryType), fetches
 * the REAL figures for it via CrmQueryCatalog (zero AI involved in that
 * step), then narrates an answer grounded only in those figures. Two AI
 * calls, not one — classify, then narrate — so the model never invents a
 * number: it only ever picks which existing report to read from.
 *
 * Admin/Manager only, matching the sibling management reports (Employee
 * Performance, AI Usage) this reuses the same gating convention from.
 */
class AskTheCrm extends Component
{
    use RatesAiDrafts;

    #[Validate('required|string|max:300')]
    public string $question = '';

    public ?string $answer = null;

    /** @var list<array{label: string, value: string}> */
    public array $figures = [];

    public ?string $reportRouteName = null;

    public ?string $reportLabel = null;

    public bool $unsupported = false;

    public bool $aiEnabled = false;

    public ?int $answerUsageId = null;

    public ?string $answerFeedback = null;

    public function mount(): void
    {
        $this->aiEnabled = Ai::enabled();
    }

    public function ask(AiAssistant $ai, CrmQueryCatalog $catalog): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $this->validate();
        $this->reset(['answer', 'figures', 'reportRouteName', 'reportLabel', 'unsupported', 'answerUsageId', 'answerFeedback']);

        $type = $ai->classifyCrmQuestion($this->question);

        if ($type === null) {
            $this->unsupported = true;

            return;
        }

        $this->figures = $catalog->run($type, auth()->user());
        $route = $type->reportRoute();
        $this->reportRouteName = $route['name'];
        $this->reportLabel = $route['label'];

        $this->answer = $ai->narrateCrmAnswer($this->question, $type, $this->figures)
            ?? 'Could not put that into words right now — the figures above are still accurate.';
        $this->answerUsageId = $ai->lastUsageId;
    }

    public function rateAnswer(string $direction): void
    {
        $this->recordAiFeedback($this->answerUsageId, $direction);
        $this->answerFeedback = $direction;
    }

    public function dismiss(): void
    {
        $this->reset(['answer', 'figures', 'reportRouteName', 'reportLabel', 'unsupported', 'question', 'answerUsageId', 'answerFeedback']);
    }

    /**
     * @return list<array{label: string, description: string}>
     */
    public function exampleTopics(): array
    {
        return collect(CrmQueryType::cases())
            ->map(fn (CrmQueryType $t) => ['label' => $t->label(), 'description' => $t->description()])
            ->all();
    }

    public function render()
    {
        return view('livewire.ask-the-crm');
    }
}
