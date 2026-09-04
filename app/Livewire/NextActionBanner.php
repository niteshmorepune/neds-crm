<?php

namespace App\Livewire;

use App\Models\NextActionSnooze;
use App\Services\NextActionEngine;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Embedded once in the main app layout so it's present on every
 * authenticated page. Polls every 45s (Hostinger has no websockets) asking
 * NextActionEngine for this user's single pending prompt; "Snooze" writes a
 * NextActionSnooze row rather than resolving anything, so the same or next
 * prompt can resurface later. Never blocks the rest of the page — dismissed/
 * snoozed by design, per the owner's explicit choice (2026-09-03). There is
 * still deliberately no true "close" — only snooze tiers (2026-09-04, in
 * response to staff finding a single fixed 30-min snooze too naggy) — so the
 * prompt can never be silently forgotten, only deferred.
 */
class NextActionBanner extends Component
{
    /** Menu order/labels for the Blade view's snooze dropdown. */
    public const SNOOZE_TIERS = [
        '30m' => 'Snooze 30 min',
        '2h' => 'Snooze 2 hours',
        'tomorrow' => 'Remind me tomorrow',
    ];

    /** @var array{source_key: string, subject_type: string, subject_id: int, title: string, body: string, action_url: ?string, action_label: string, external: bool}|null */
    public ?array $action = null;

    public function mount(NextActionEngine $engine): void
    {
        $this->refreshAction($engine);
    }

    public function poll(NextActionEngine $engine): void
    {
        $this->refreshAction($engine);
    }

    public function snooze(NextActionEngine $engine, string $tier = '30m'): void
    {
        abort_unless($this->action !== null, 404);
        abort_unless(array_key_exists($tier, self::SNOOZE_TIERS), 422);

        NextActionSnooze::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'source_key' => $this->action['source_key'],
                'subject_type' => $this->action['subject_type'],
                'subject_id' => $this->action['subject_id'],
            ],
            ['snoozed_until' => $this->snoozedUntil($tier)],
        );

        $this->refreshAction($engine);
    }

    private function snoozedUntil(string $tier): Carbon
    {
        return match ($tier) {
            '30m' => now()->addMinutes(30),
            '2h' => now()->addHours(2),
            'tomorrow' => Carbon::now(config('app.display_timezone', 'Asia/Kolkata'))
                ->addDay()->setTime(9, 0)->utc(),
        };
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
