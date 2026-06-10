<?php

namespace App\Enums;

enum CallOutcome: string
{
    case Connected = 'connected';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case FollowUpNeeded = 'follow_up_needed';

    public function label(): string
    {
        return match ($this) {
            self::NoAnswer => 'No Answer',
            self::FollowUpNeeded => 'Follow-up Needed',
            default => ucfirst($this->value),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
