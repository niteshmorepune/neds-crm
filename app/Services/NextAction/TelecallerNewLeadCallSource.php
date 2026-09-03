<?php

namespace App\Services\NextAction;

use App\Enums\UserRole;

/**
 * Phase 5 of the Next Action Engine: the Telecaller counterpart of
 * SalesNewLeadCallSource, against `leads.telecaller_id` (real per-telecaller
 * assignment, see [[lead-visibility-telecaller-assignment]]) instead of
 * `owner_id`. See AbstractNewLeadCallSource for the shared shape.
 */
class TelecallerNewLeadCallSource extends AbstractNewLeadCallSource
{
    public function key(): string
    {
        return 'telecaller_new_lead_call';
    }

    protected function role(): UserRole
    {
        return UserRole::Telecaller;
    }

    protected function ownerColumn(): string
    {
        return 'telecaller_id';
    }
}
