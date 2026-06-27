<?php

namespace App\Http\Controllers\Portal;

use Illuminate\View\View;

class QuotationController extends PortalController
{
    public function index(): View
    {
        $quotations = $this->customer()
            ->quotations()
            ->with('items')
            ->latest()
            ->paginate(15);

        return view('portal.quotations.index', compact('quotations'));
    }
}
