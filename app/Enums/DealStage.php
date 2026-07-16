<?php

namespace App\Enums;

enum DealStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Terminal stages cannot transition to another stage.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Won, self::Lost], true);
    }

    /**
     * Rough likelihood (%) that a deal in this stage eventually closes Won.
     * A generic linear ramp — not calibrated from historical conversion data
     * yet, so treat the weighted-forecast figure it feeds as indicative only.
     */
    public function probability(): int
    {
        return match ($this) {
            self::New => 10,
            self::Contacted => 25,
            self::Proposal => 50,
            self::Negotiation => 75,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    /**
     * Stages shown as Kanban columns, in order.
     *
     * @return array<int, self>
     */
    public static function columns(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
