<?php

namespace App\Livewire;

use App\Models\NextActionSnooze;
use App\Services\NextActionEngine;
use Livewire\Component;

/**
 * Embedded once in the main app layout so it's present on every
 * authenticated page. Polls every 45s (Hostinger has no websockets) asking
 * NextActionEngine for this user's single pending prompt; "Snooze" writes a
 * NextActionSnooze row rather than resolving anything, so the same or next
 * prompt can resurface later. Never blocks the rest of the page — dismissed/
 * snoozed by design, per the owner's explicit choice (2026-09-03).
 */
class NextActionBanner extends Component
{
    /** @var array{source_key: string, subject_type: string, subject_id: int, title: string, body: string, action_url: string, action_label: string}|null */
    public ?array $action = null;

    public function mount(NextActionEngine $engine): void
    {
        $this->refreshAction($engine);
    }

    public function poll(NextActionEngine $engine): void
    {
        $this->refreshAction($engine);
    }

    public function snooze(NextActionEngine $engine): void
    {
        abort_unless($this->action !== null, 404);

        NextActionSnooze::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'source_key' => $this->action['source_key'],
                'subject_type' => $this->action['subject_type'],
                'subject_id' => $this->action['subject_id'],
            ],
            ['snoozed_until' => now()->addMinutes(30)],
        );

        $this->refreshAction($engine);
    }

    public function complete(NextActionEngine $engine): void
    {
        abort_unless($this->action !== null, 404);

        $engine->completeFor(auth()->user(), $this->action['source_key'], $this->action['subject_id']);

        $this->refreshAction($engine);
    }

    private function refreshAction(NextActionEngine $engine): void
    {
        $this->action = auth()->check()
            ? $engine->nextFor(auth()->user())?->toArray()
            : null;
    }

    public function render()
    {
        return view('livewire.next-action-banner');
    }
}
