<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
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

        return view('notifications.index', compact('notifications', 'deletedInvoiceIds'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return back()->with('status', 'Notification dismissed.');
    }
}
