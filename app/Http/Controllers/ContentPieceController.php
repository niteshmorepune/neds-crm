<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Http\Requests\StoreContentPieceRequest;
use App\Http\Requests\UpdateContentPieceRequest;
use App\Models\ContentPiece;
use App\Models\Partner;
use App\Models\Project;
use Illuminate\Http\Request;

class ContentPieceController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('viewAny', [ContentPiece::class, $project]);

        $pieces = $project->contentPieces()
            ->with('partner')
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->when(request('platform'), fn ($q) => $q->where('platform', request('platform')))
            ->orderBy('publish_date')
            ->orderByDesc('created_at')
            ->get();

        $partners = Partner::orderBy('name')->get();

        return view('content-pieces.index', compact('project', 'pieces', 'partners'));
    }

    public function create(Project $project)
    {
        $this->authorize('create', [ContentPiece::class, $project]);

        $partners = Partner::orderBy('name')->get();

        return view('content-pieces.create', compact('project', 'partners'));
    }

    public function store(StoreContentPieceRequest $request, Project $project)
    {
        $this->authorize('create', [ContentPiece::class, $project]);

        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['created_by'] = auth()->id();
        $workflowType = ContentWorkflowType::from($data['workflow_type']);
        $data['status'] = ContentStatus::initialFor($workflowType)->value;

        ContentPiece::create($data);

        return redirect()->route('projects.content.index', $project)
            ->with('status', 'Content piece added.');
    }

    public function show(ContentPiece $contentPiece)
    {
        $this->authorize('view', $contentPiece);

        $contentPiece->load('project', 'partner', 'creator', 'attachments');

        $allowedNext = ContentStatus::allowedNextFor($contentPiece->workflow_type)[$contentPiece->status->value] ?? [];

        return view('content-pieces.show', compact('contentPiece', 'allowedNext'));
    }

    public function edit(ContentPiece $contentPiece)
    {
        $this->authorize('update', $contentPiece);

        $partners = Partner::orderBy('name')->get();

        return view('content-pieces.edit', compact('contentPiece', 'partners'));
    }

    public function update(UpdateContentPieceRequest $request, ContentPiece $contentPiece)
    {
        $this->authorize('update', $contentPiece);

        $contentPiece->update($request->validated());

        return redirect()->route('content.show', $contentPiece)
            ->with('status', 'Content piece updated.');
    }

    public function destroy(ContentPiece $contentPiece)
    {
        $this->authorize('delete', $contentPiece);

        $project = $contentPiece->project;
        $contentPiece->delete();

        return redirect()->route('projects.content.index', $project)
            ->with('status', 'Content piece deleted.');
    }

    public function generateUploadLink(Project $project, ContentPiece $contentPiece)
    {
        $this->authorize('generateUploadLink', $contentPiece);

        $url = $contentPiece->generateUploadToken();

        return back()->with('upload_link', $url)
            ->with('status', 'Upload link generated — valid for 7 days.');
    }

    public function advance(Request $request, Project $project, ContentPiece $contentPiece)
    {
        $this->authorize('update', $contentPiece);

        $allowed = ContentStatus::allowedNextFor($contentPiece->workflow_type)[$contentPiece->status->value] ?? [];

        $request->validate([
            'status' => ['required', 'in:'.implode(',', $allowed)],
        ]);

        $newStatus = ContentStatus::from($request->status);

        $data = ['status' => $newStatus->value];
        if ($newStatus === ContentStatus::Published) {
            $data['published_at'] = now();
        }

        $contentPiece->update($data);

        return back()->with('status', 'Status updated to '.$newStatus->label().'.');
    }
}
