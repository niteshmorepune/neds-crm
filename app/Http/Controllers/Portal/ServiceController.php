<?php

namespace App\Http\Controllers\Portal;

use Illuminate\View\View;

class ServiceController extends PortalController
{
    public function index(): View
    {
        $customer = $this->customer()->load([
            'projects.service',
            'projects.owner',
            'projects.assignees',
            'recurringInvoices' => fn ($q) => $q->where('is_active', true)->with('service'),
            'owner',
        ]);

        return view('portal.services.index', [
            'projects'   => $customer->projects->sortBy(fn ($p) => $p->service?->name),
            'recurring'  => $customer->recurringInvoices->sortBy(fn ($r) => $r->service?->name),
            'accountOwner' => $customer->owner,
        ]);
    }
}
