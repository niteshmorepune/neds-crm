<?php

namespace App\Services\NextAction;

use App\Enums\UserRole;

/**
 * Phase 1 of the Next Action Engine (see CLAUDE.md decisions log,
 * 2026-09-03). See AbstractNewLeadCallSource for the shared shape shared
 * with TelecallerNewLeadCallSource.
 */
class SalesNewLeadCallSource extends AbstractNewLeadCallSource
{
    public function key(): string
    {
        return 'sales_new_lead_call';
    }

    protected function role(): UserRole
    {
        return UserRole::Sales;
    }

    protected function ownerColumn(): string
    {
        return 'owner_id';
    }
}
