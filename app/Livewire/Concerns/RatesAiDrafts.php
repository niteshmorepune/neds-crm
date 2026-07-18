<?php

namespace App\Livewire\Concerns;

use App\Models\AiUsage;

/**
 * One optional click after a person has actually looked at an AI draft/
 * answer/suggestion — did it need heavy rewriting or barely a touch. Every
 * component that shows AI output captures AiAssistant::$lastUsageId right
 * after the call it's about to display, then calls recordAiFeedback() with
 * that id once the person clicks. Rolls up into the AI Usage Report as a
 * real quality signal per feature, not just a call count.
 */
trait RatesAiDrafts
{
    protected function recordAiFeedback(?int $usageId, string $direction): void
    {
        if ($usageId === null) {
            return;
        }

        abort_unless(in_array($direction, ['up', 'down'], true), 422);

        AiUsage::whereKey($usageId)->update(['feedback' => $direction]);
    }
}
