<?php

namespace App\Enums;

/**
 * Bounded department taxonomy for Important Links (which team the link is
 * primarily for) — nullable on the model, not a case here, since "no
 * department set yet" is a real state existing links land in (see the
 * 2026-08-06 migration), not itself a category someone would pick.
 */
enum LinkDepartment: string
{
    case Sales = 'sales';
    case Support = 'support';
    case Accounts = 'accounts';
    case Admin = 'admin';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
