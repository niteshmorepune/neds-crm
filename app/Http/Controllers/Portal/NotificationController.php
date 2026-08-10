<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends PortalController
{
    public function index(): View
    {
        $contact = auth('portal')->user();

        $notifications = $contact->notifications()->latest()->paginate(20);
        $contact->unreadNotifications()->update(['read_at' => now()]);

        return view('portal.notifications.index', compact('notifications'));
    }

    public function destroy(string $id): RedirectResponse
    {
        auth('portal')->user()->notifications()->where('id', $id)->delete();

        return back()->with('status', 'Notification dismissed.');
    }
}
