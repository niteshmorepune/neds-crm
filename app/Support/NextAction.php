<?php

namespace App\Support;

/**
 * One "do this now" prompt for the NextActionBanner. Immutable — a
 * NextActionSource builds one fresh on every poll, never mutated in place.
 *
 * `actionUrl` is nullable: when set, the banner renders a plain link (the
 * action happens on another page, e.g. the Log a Call form — no source
 * method needed). When null, the banner renders a button that calls back
 * into `NextActionSource::complete()` for a one-click, in-place action
 * (e.g. attendance check-in) — never both at once.
 */
final class NextAction
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $actionUrl,
        public readonly string $actionLabel,
    ) {}

    /** @return array{source_key: string, subject_type: string, subject_id: int, title: string, body: string, action_url: ?string, action_label: string} */
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
