<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Http\Controllers\Controller;
use App\Models\Partner;

/**
 * Base for all partner portal controllers. Provides the logged-in partner
 * every query must be scoped to — nothing in the partner portal may reach
 * another partner's data.
 */
abstract class PartnerPortalController extends Controller
{
    protected function partner(): Partner
    {
        return auth('partner')->user();
    }
}
