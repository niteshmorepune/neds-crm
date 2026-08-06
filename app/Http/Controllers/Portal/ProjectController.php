<?php

namespace App\Http\Controllers\Portal;

use App\Enums\DeliverableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends PortalController
{
    /**
     * Allowed portal attachment types — same narrower list as the portal
     * ticket upload, since this is also a public-facing form.
     */
    private const ATTACHMENT_RULES = ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv,zip'];

    public function index(): View
    {
        return view('portal.projects.index', [
            'projects' => $this->customer()->projects()->with(['service', 'owner', 'assignees'])->latest()->paginate(15),
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
            'deliverables' => fn ($q) => $q->with('attachments')->latest(),
        ]);

        return view('portal.projects.show', ['project' => $project]);
    }

    public function uploadDeliverable(Request $request, int $project, int $deliverable): RedirectResponse
    {
        $project = $this->customer()->projects()->findOrFail($project);
        $deliverable = $project->deliverables()->findOrFail($deliverable);

        $data = $request->validate(['attachment' => self::ATTACHMENT_RULES]);

        $file = $data['attachment'];
        $deliverable->attachments()->create([
            'contact_id' => auth('portal')->id(),
            'disk' => 'local',
            'path' => $file->store('attachments', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        if ($deliverable->status === DeliverableStatus::Pending) {
            $deliverable->update(['status' => DeliverableStatus::Submitted]);
        }

        return back()->with('status', 'File uploaded — thank you!');
    }
}
