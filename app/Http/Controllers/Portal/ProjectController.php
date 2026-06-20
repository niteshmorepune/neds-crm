<?php

namespace App\Http\Controllers\Portal;

use Illuminate\View\View;

class ProjectController extends PortalController
{
    public function index(): View
    {
        return view('portal.projects.index', [
            'projects' => $this->customer()->projects()->with(['service', 'owner'])->latest()->paginate(15),
        ]);
    }

    public function show(int $project): View
    {
        $project = $this->customer()->projects()->findOrFail($project);
        $project->load([
            'notes' => fn ($q) => $q->where('visible_to_client', true)->with('author'),
            'service',
            'owner',
            'assignees',
        ]);

        return view('portal.projects.show', ['project' => $project]);
    }
}
