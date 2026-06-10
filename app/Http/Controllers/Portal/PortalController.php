<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;

/**
 * Base for all portal controllers. Provides the single customer every query
 * must be scoped to — the logged-in contact's company. Nothing in the portal
 * may reach another customer's data.
 */
abstract class PortalController extends Controller
{
    protected function customer(): Customer
    {
        return auth('portal')->user()->customer;
    }
}
