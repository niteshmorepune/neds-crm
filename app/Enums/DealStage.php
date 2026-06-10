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
