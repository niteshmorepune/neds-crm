<?php

namespace App\Http\Controllers\Portal;

use App\Models\Announcement;
use Illuminate\View\View;

class HomeController extends PortalController
{
    public function index(): View
    {
        $customer = $this->customer();

        return view('portal.home', [
            'customer' => $customer,
            'openInvoices' => $customer->invoices()
                ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])->count(),
            'openTickets' => $customer->tickets()->open()->count(),
            'activeProjects' => $customer->projects()->where('status', 'active')->count(),
            'announcements' => Announcement::active()->forClients()->newestFirst()->get(),
        ]);
    }
}
