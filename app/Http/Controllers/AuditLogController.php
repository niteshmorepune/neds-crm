<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only viewer over the `activities` audit trail (create/update/delete on
 * core models, recorded by the LogsActivity trait).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $activities = Activity::query()
            ->with('user')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')->value()))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')->value()))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('audit-log.index', [
            'activities' => $activities,
            'subjectTypes' => Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'filters' => $request->only(['user_id', 'subject_type', 'event']),
        ]);
    }
}
