<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Attendance::class);

        $month = $this->month($request);

        $records = Attendance::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString());

        return view('attendance.index', [
            'month' => $month,
            'records' => $records,
        ]);
    }

    public function corrections(Request $request): View
    {
        $this->authorize('correct', Attendance::class);

        $date = $request->date('date') ?? Carbon::today();

        $users = User::orderBy('name')->get();
        $entries = Attendance::whereDate('date', $date)->get()->keyBy('user_id');

        return view('attendance.corrections', [
            'date' => $date,
            'users' => $users,
            'entries' => $entries,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function storeCorrection(Request $request): RedirectResponse
    {
        $this->authorize('correct', Attendance::class);

        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in_at' => ['nullable', 'date_format:H:i'],
            'check_out_at' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $date = Carbon::parse($data['date']);

        $attendance = Attendance::where('user_id', $data['user_id'])->whereDate('date', $date)->first()
            ?? new Attendance(['user_id' => $data['user_id'], 'date' => $date->toDateString()]);

        $attendance->fill([
            'status' => $data['status'],
            'check_in_at' => ! empty($data['check_in_at']) ? $date->copy()->setTimeFromTimeString($data['check_in_at']) : null,
            'check_out_at' => ! empty($data['check_out_at']) ? $date->copy()->setTimeFromTimeString($data['check_out_at']) : null,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return back()->with('status', 'Attendance corrected.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $month = $this->month($request);
        $isManager = $request->user()->hasRole(UserRole::Admin, UserRole::Manager);

        $records = Attendance::query()
            ->with('user')
            ->when(! $isManager, fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('date')
            ->get();

        $filename = 'attendance-'.$month->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['User', 'Date', 'Status', 'Check in', 'Check out']);
            foreach ($records as $r) {
                fputcsv($out, [
                    $r->user?->name,
                    $r->date->toDateString(),
                    $r->status->label(),
                    $r->check_in_at?->timezone(config('app.timezone'))->format('H:i'),
                    $r->check_out_at?->timezone(config('app.timezone'))->format('H:i'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function month(Request $request): Carbon
    {
        return $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : Carbon::today()->startOfMonth();
    }
}
