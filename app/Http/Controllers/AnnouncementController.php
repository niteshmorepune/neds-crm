<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/manager management of the Notice Board — time-bound posts shown as a
 * banner on the staff Dashboard, the Client Portal home page, or both,
 * depending on the post's audience.
 */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcements.index', [
            'announcements' => Announcement::newestFirst()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcements.create');
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        Announcement::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('announcements.index')->with('status', 'Announcement posted.');
    }

    public function edit(Announcement $announcement): View
    {
        $this->authorize('update', $announcement);

        return view('announcements.edit', compact('announcement'));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $announcement->update($request->validated());

        return redirect()->route('announcements.index')->with('status', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('announcements.index')->with('status', 'Announcement removed.');
    }
}
