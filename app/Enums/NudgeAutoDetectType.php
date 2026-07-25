<?php

namespace App\Enums;

/**
 * The bounded catalog of activity checks a weekly TeamNudge can auto-clear
 * against. Each case maps 1:1 to a real Eloquent check in
 * App\Services\TeamNudgeDetector — never a free-form/AI-guessed check.
 */
enum NudgeAutoDetectType: string
{
    case DealsLoggedThisWeek = 'deals_logged_this_week';
    case TicketsLoggedThisWeek = 'tickets_logged_this_week';

    public function label(): string
    {
        return match ($this) {
            self::DealsLoggedThisWeek => 'A Deal logged this week',
            self::TicketsLoggedThisWeek => 'A Ticket logged this week',
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
