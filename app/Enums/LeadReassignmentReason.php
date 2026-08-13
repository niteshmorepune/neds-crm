<?php

namespace App\Enums;

enum LeadReassignmentReason: string
{
    case OnLeave = 'on_leave';
    case LeftCompany = 'left_company';
    case Rebalancing = 'rebalancing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OnLeave => 'On leave',
            self::LeftCompany => 'Left the company',
            self::Rebalancing => 'Rebalancing workload',
            self::Other => 'Other',
        };
    }
}
