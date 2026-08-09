<?php

namespace App\Enums;

enum ContractRenewalStatus: string
{
    case NotStarted = 'not_started';
    case Discussion = 'discussion';
    case ProposalSent = 'proposal_sent';
    case Negotiation = 'negotiation';
    case Renewed = 'renewed';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Discussion => 'Discussion',
            self::ProposalSent => 'Proposal Sent',
            self::Negotiation => 'Negotiation',
            self::Renewed => 'Renewed',
            self::Lost => 'Lost',
        };
    }

    /** Renewed/Lost are the two ways a renewal conversation ends — no further status change is expected after either. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Renewed, self::Lost], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-gray-100 text-gray-600',
            self::Discussion => 'bg-blue-100 text-blue-700',
            self::ProposalSent => 'bg-amber-100 text-amber-700',
            self::Negotiation => 'bg-orange-100 text-orange-700',
            self::Renewed => 'bg-green-100 text-green-700',
            self::Lost => 'bg-red-100 text-red-700',
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
