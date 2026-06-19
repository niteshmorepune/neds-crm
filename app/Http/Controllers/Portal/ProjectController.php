<?php

namespace App\Http\Controllers\Portal;

use Illuminate\View\View;

class ProjectController extends PortalController
{
    public function index(): View
    {
        return view('portal.projects.index', [
            'projects' => $this->customer()->projects()->latest()->paginate(15),
        ]);
    }

    public function show(int $project): View
    {
        $project = $this->customer()->projects()->findOrFail($project);
        $project->load(['notes.author', 'service']);

        return view('portal.projects.show', ['project' => $project]);
    }
}
