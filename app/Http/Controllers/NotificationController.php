<?php

namespace App\Http\Controllers;

use App\Enums\QuotationApprovalStatus;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        $invoiceIds = $notifications->getCollection()
            ->map(fn ($notification) => $notification->data['invoice_id'] ?? null)
            ->filter()
            ->unique();

        $deletedInvoiceIds = $invoiceIds->isEmpty()
            ? collect()
            : Invoice::onlyTrashed()->whereIn('id', $invoiceIds)->pluck('id');

        $dealIds = $notifications->getCollection()
            ->map(fn ($notification) => $notification->data['deal_id'] ?? null)
            ->filter()
            ->unique();

        $deletedDealIds = $dealIds->isEmpty()
            ? collect()
            : Deal::onlyTrashed()->whereIn('id', $dealIds)->pluck('id');

        $leadIds = $notifications->getCollection()
            ->map(fn ($notification) => $notification->data['lead_id'] ?? null)
            ->filter()
            ->unique();

        $deletedLeadIds = $leadIds->isEmpty()
            ? collect()
            : Lead::onlyTrashed()->whereIn('id', $leadIds)->pluck('id');

        $pendingApprovalQuotationIds = $notifications->getCollection()
            ->filter(fn ($notification) => ($notification->data['type'] ?? null) === 'quotation_needs_approval')
            ->map(fn ($notification) => $notification->data['quotation_id'] ?? null)
            ->filter()
            ->unique();

        $resolvedQuotations = $pendingApprovalQuotationIds->isEmpty()
            ? collect()
            : Quotation::with('approvedBy')
                ->whereIn('id', $pendingApprovalQuotationIds)
                ->where('approval_status', '!=', QuotationApprovalStatus::Pending->value)
                ->get()
                ->keyBy('id');

        return view('notifications.index', compact('notifications', 'deletedInvoiceIds', 'deletedDealIds', 'deletedLeadIds', 'resolvedQuotations'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return back()->with('status', 'Notification dismissed.');
    }
}
