<?php

namespace App\Livewire;

use App\Enums\DealLostReason;
use App\Models\Deal;
use App\Services\AiAssistant;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Why was this deal lost?" field on the deal edit page (deals/show.blade.php),
 * revealed via Alpine x-show once the Stage select is set to Lost. Renders a plain
 * <select name="lost_reason"> that participates in the surrounding native form's
 * POST like any other field -- this component's only job is to ask Claude for a
 * suggestion and pre-select it; it never submits anything itself. Mirrors
 * DealsBoard::suggestLostReason() for the board's own drag-and-drop picker.
 *
 * The suggested reason is deliberately never pre-selected as the field's
 * actual value (only labeled "(suggested)" next to the right option) --
 * Pipeline Playbook gap idea #2 (2026-09-04): a pre-filled dropdown let a
 * rep save the whole edit form without ever engaging with this field at
 * all, since DealUpdateRequest's `required_if:stage,lost` was already
 * satisfied by the untouched AI guess. Picking a reason now always takes a
 * real click, same as DealsBoard's own Lost-column button picker already
 * required from day one.
 *
 * suggest() is triggered via a dispatched browser event (deal-stage-set-to-lost,
 * fired by the Stage <select>'s own x-on:change) rather than a direct $wire call
 * from the parent markup -- this component sits outside the Stage select's own
 * Alpine/$wire scope (the surrounding form is plain Blade, not a Livewire
 * component), so a page-wide event is the reliable way to reach it regardless of
 * DOM position.
 */
class DealLostReasonField extends Component
{
    public Deal $deal;

    public ?string $suggestedReason = null;

    public ?string $rationale = null;

    /** Guards against a second Claude call if suggest() is somehow triggered twice. */
    public bool $requested = false;

    public function mount(Deal $deal): void
    {
        $this->deal = $deal;
    }

    #[On('deal-stage-set-to-lost')]
    public function suggest(AiAssistant $assistant): void
    {
        abort_unless(auth()->user()?->can('update', $this->deal), 403);

        if ($this->requested) {
            return;
        }

        $this->requested = true;

        $result = $assistant->suggestDealLostReason($this->deal);

        if ($result !== null) {
            $this->suggestedReason = $result['reason']?->value;
            $this->rationale = $result['rationale'];

            // Persisted regardless of what the rep ends up picking -- see
            // Deal::aiSuggestionOutcome(), the Loss Reason report's
            // calibration signal on Phase 1's own suggestion quality.
            $this->deal->forceFill(['ai_suggested_lost_reason' => $result['reason']?->value])->saveQuietly();
        }
    }

    public function render()
    {
        return view('livewire.deal-lost-reason-field', [
            'reasons' => DealLostReason::cases(),
        ]);
    }
}
