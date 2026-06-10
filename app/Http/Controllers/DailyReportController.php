<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\User;
use App\Services\DailyReportMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DailyReportController extends Controller
{
    public function index(Request $request, DailyReportMetrics $metrics): View
    {
        $this->authorize('viewAny', DailyReport::class);

        $user = $request->user();
        $today = Carbon::today();

        return view('daily-reports.index', [
            'metrics' => $metrics->for($user, $today),
            'todayReport' => DailyReport::where('user_id', $user->id)->whereDate('date', $today)->first(),
            'history' => DailyReport::where('user_id', $user->id)->latest('date')->paginate(15),
            'canViewTeam' => $user->can('viewTeam', DailyReport::class),
        ]);
    }

    public function store(Request $request, DailyReportMetrics $metrics): RedirectResponse
    {
        $this->authorize('viewAny', DailyReport::class);

        $data = $request->validate(['summary' => ['required', 'string', 'max:5000']]);

        $user = $request->user();
        $today = Carbon::today();

        // Match on the date part (whereDate) so re-submitting the same day
        // updates rather than duplicates, regardless of DB date storage.
        $report = DailyReport::where('user_id', $user->id)->whereDate('date', $today)->first()
            ?? new DailyReport(['user_id' => $user->id, 'date' => $today->toDateString()]);

        $report->fill($metrics->for($user, $today) + [
            'summary' => $data['summary'],
            'submitted_at' => now(),
        ])->save();

        return back()->with('status', 'Daily report submitted.');
    }

    public function team(Request $request): View
    {
        $this->authorize('viewTeam', DailyReport::class);

        $date = $request->date('date') ?? Carbon::today();

        return view('daily-reports.team', [
            'date' => $date,
            'users' => User::orderBy('name')->get(),
            'reports' => DailyReport::whereDate('date', $date)->get()->keyBy('user_id'),
        ]);
    }
}
