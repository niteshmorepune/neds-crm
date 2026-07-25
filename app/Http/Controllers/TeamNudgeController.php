<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamNudgeRequest;
use App\Models\TeamNudge;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/manager management of Team Nudges — reusable, targeted reminders
 * shown on each staff member's own dashboard (see App\Livewire\MyTeamNudges),
 * with a team-wide completion overview (App\Livewire\TeamNudgeOverview)
 * embedded on the index page.
 */
class TeamNudgeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TeamNudge::class);

        return view('team-nudges.index', [
            'nudges' => TeamNudge::query()->with('creator')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TeamNudge::class);

        return view('team-nudges.create');
    }

    public function store(TeamNudgeRequest $request): RedirectResponse
    {
        $this->authorize('create', TeamNudge::class);

        TeamNudge::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('team-nudges.index')->with('status', 'Nudge created.');
    }

    public function edit(TeamNudge $teamNudge): View
    {
        $this->authorize('update', $teamNudge);

        return view('team-nudges.edit', ['nudge' => $teamNudge]);
    }

    public function update(TeamNudgeRequest $request, TeamNudge $teamNudge): RedirectResponse
    {
        $this->authorize('update', $teamNudge);

        $teamNudge->update($request->validated());

        return redirect()->route('team-nudges.index')->with('status', 'Nudge updated.');
    }

    public function destroy(TeamNudge $teamNudge): RedirectResponse
    {
        $this->authorize('delete', $teamNudge);

        $teamNudge->delete();

        return redirect()->route('team-nudges.index')->with('status', 'Nudge removed.');
    }
}
