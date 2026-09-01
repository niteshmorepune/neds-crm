<?php

namespace App\Enums;

/**
 * Captured on the point of moving a Deal to Lost — see Deal::moveToStage().
 * Fixed set for now (no free-text "Other"). AI-suggested via
 * AiAssistant::suggestDealLostReason(); the suggestion itself is separately
 * persisted on Deal::$ai_suggested_lost_reason for calibration reporting —
 * see Deal::aiSuggestionOutcome().
 */
enum DealLostReason: string
{
    case Price = 'price';
    case Timing = 'timing';
    case Competitor = 'competitor';
    case WentDark = 'went_dark';
    case NotAFit = 'not_a_fit';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Price',
            self::Timing => 'Bad timing',
            self::Competitor => 'Chose a competitor',
            self::WentDark => 'Went dark / unresponsive',
            self::NotAFit => 'Not a good fit',
        };
    }
}
