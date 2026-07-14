<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\BiometricSyncStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\BiometricSyncRequest;
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

        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);
        $month = $this->month($request);

        // Managers/admins may select any user; employees always see their own.
        $viewingUser = $user;
        $users = null;
        if ($isManager) {
            // Admins see everyone; managers see everyone except admins.
            $users = User::orderBy('name')
                ->when(! $user->hasRole(UserRole::Admin), fn ($q) => $q->where('role', '!=', UserRole::Admin->value))
                ->get(['id', 'name']);
            $selectedId = $request->integer('user_id', $user->id);
            $viewingUser = $users->firstWhere('id', $selectedId) ?? $user;
        }

        $records = Attendance::query()
            ->where('user_id', $viewingUser->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString());

        return view('attendance.index', [
            'month' => $month,
            'records' => $records,
            'isManager' => $isManager,
            'users' => $users,
            'viewingUser' => $viewingUser,
            'latestSync' => $isManager ? BiometricSyncRequest::latest('requested_at')->first() : null,
        ]);
    }

    /**
     * Queues a manual biometric sync request. The CRM has no network path to
     * the device (office LAN only), so this doesn't sync anything itself —
     * the office-LAN bridge script polls for a pending request every minute
     * and does the actual work (see check-manual-sync.mjs).
     */
    public function requestSync(Request $request): RedirectResponse
    {
        $this->authorize('correct', Attendance::class);

        $existing = BiometricSyncRequest::where('status', BiometricSyncStatus::Pending)->exists();

        if (! $existing) {
            BiometricSyncRequest::create([
                'requested_by_id' => $request->user()->id,
                'requested_at' => now(),
                'status' => BiometricSyncStatus::Pending,
            ]);
        }

        return back()->with('status', 'Sync requested — the office bridge checks every minute and will pick it up shortly.');
    }

    public function corrections(Request $request): View
    {
        $this->authorize('correct', Attendance::class);

        $date = $request->date('date') ?? Carbon::today();

        $users = User::orderBy('name')
            ->when(! $request->user()->hasRole(UserRole::Admin), fn ($q) => $q->where('role', '!=', UserRole::Admin->value))
            ->get();
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
            'user_id'    => ['required', Rule::exists('users', 'id')],
            'date'       => ['required', 'date'],
            'status'     => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in'   => ['nullable', 'date_format:H:i'],
            'check_out'  => ['nullable', 'date_format:H:i'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $date = Carbon::parse($data['date']);
        $tz = config('app.display_timezone', 'Asia/Kolkata');

        $attendance = Attendance::where('user_id', $data['user_id'])->whereDate('date', $date)->first()
            ?? new Attendance(['user_id' => $data['user_id'], 'date' => $date->toDateString()]);

        $fill = [
            'status' => $data['status'],
            'notes'  => $data['notes'] ?? null,
        ];

        if (filled($data['check_in'] ?? null)) {
            $fill['check_in_at'] = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$data['check_in'], $tz)->utc();
        }

        if (filled($data['check_out'] ?? null)) {
            $fill['check_out_at'] = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$data['check_out'], $tz)->utc();
        }

        $attendance->fill($fill)->save();

        return back()->with('status', 'Attendance corrected.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $month = $this->month($request);
        $currentUser = $request->user();
        $isManager = $currentUser->hasRole(UserRole::Admin, UserRole::Manager);
        $filterUserId = $isManager && $request->filled('user_id') ? $request->integer('user_id') : null;

        $records = Attendance::query()
            ->with('user')
            ->when(! $isManager, fn ($q) => $q->where('user_id', $currentUser->id))
            ->when($isManager && $filterUserId, fn ($q) => $q->where('user_id', $filterUserId))
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
                    $r->check_in_at?->timezone(config('app.display_timezone'))->format('H:i'),
                    $r->check_out_at?->timezone(config('app.display_timezone'))->format('H:i'),
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
