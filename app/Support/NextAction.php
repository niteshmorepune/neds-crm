<?php

namespace App\Support;

/**
 * One "do this now" prompt for the NextActionBanner. Immutable — a
 * NextActionSource builds one fresh on every poll, never mutated in place.
 */
final class NextAction
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
    ) {}

    /** @return array{source_key: string, subject_type: string, subject_id: int, title: string, body: string, action_url: string, action_label: string} */
    public function toArray(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ];
    }
}
