<?php

namespace App\Http\Controllers;

use App\Actions\CreateProjectFromDeal;
use App\Enums\DealStage;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Requests\ProjectStoreRequest;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $projects = Project::query()
            ->with(['customer', 'owner'])
            ->withCount('tasks')
            ->unless($user->hasRole(UserRole::Admin, UserRole::Manager), fn ($q) => $q->where(function ($w) use ($user) {
                $w->where('owner_id', $user->id)
                    ->orWhereHas('assignees', fn ($a) => $a->whereKey($user->id));
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', $this->formData() + [
            'projects' => $projects,
            'filters' => $request->only('status'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create', $this->formData() + ['project' => new Project(['status' => ProjectStatus::Active->value])]);
    }

    public function store(ProjectStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validated();
        $project = Project::create($data);
        $project->assignees()->sync($data['assignees'] ?? []);

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function storeFromDeal(Deal $deal, CreateProjectFromDeal $action): RedirectResponse
    {
        $this->authorize('create', Project::class);

        if ($deal->stage !== DealStage::Won) {
            return back()->withErrors(['project' => 'Only a won deal can become a project.']);
        }

        if (Project::where('deal_id', $deal->id)->exists()) {
            return redirect()->route('projects.show', Project::where('deal_id', $deal->id)->first())
                ->with('status', 'This deal already has a project.');
        }

        $project = $action->handle($deal);

        return redirect()->route('projects.show', $project)->with('status', 'Project created from deal.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['customer', 'owner', 'assignees', 'service', 'deal']);
        $tasks = $project->tasks()->with('assignee')->latest()->get();

        return view('projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'canManage' => $this->user()->can('update', $project),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        $project->load('assignees');

        return view('projects.edit', $this->formData() + ['project' => $project]);
    }

    public function update(ProjectStoreRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();
        $project->update($data);
        $project->assignees()->sync($data['assignees'] ?? []);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => ProjectStatus::cases(),
        ];
    }

    private function user(): User
    {
        return auth()->user();
    }
}
