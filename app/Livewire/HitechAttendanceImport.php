<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use App\Support\HitechAttendanceParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('layouts.app')]
class HitechAttendanceImport extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?int $userId = null;

    public $file;

    /** @var array<int, array{date: string, entry: ?string, exit: ?string, current_in: ?string, current_out: ?string}> */
    public array $preview = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('correct', Attendance::class), 403);
    }

    /** @return Collection<int, User> */
    public function getUsersProperty(): Collection
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    public function parse(): void
    {
        $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:2048'],
        ]);

        try {
            $rows = (new HitechAttendanceParser)->parse($this->file->getRealPath());
        } catch (RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        if (count($rows) === 0) {
            $this->addError('file', 'No attendance rows found in this file.');

            return;
        }

        $tz = config('app.display_timezone', 'Asia/Kolkata');
        $existing = Attendance::where('user_id', $this->userId)
            ->whereIn('date', array_column($rows, 'date'))
            ->get()
            ->keyBy(fn (Attendance $a) => $a->date->toDateString());

        $this->preview = array_map(function (array $row) use ($existing, $tz) {
            $current = $existing->get($row['date']);

            return [
                'date' => $row['date'],
                'entry' => $row['entry'],
                'exit' => $row['exit'],
                'current_in' => $current?->check_in_at?->timezone($tz)->format('H:i:s'),
                'current_out' => $current?->check_out_at?->timezone($tz)->format('H:i:s'),
            ];
        }, $rows);

        // Keep rows out of the Livewire snapshot; re-derive from $preview on import.
        session(['hitech_import_rows' => $rows, 'hitech_import_user_id' => $this->userId]);
        $this->file = null;
        $this->step = 2;
    }

    public function import(): void
    {
        abort_unless(auth()->user()?->can('correct', Attendance::class), 403);

        $userId = session('hitech_import_user_id');
        $rows = session('hitech_import_rows', []);
        $tz = config('app.display_timezone', 'Asia/Kolkata');
        $imported = 0;

        foreach ($rows as $row) {
            $attendance = Attendance::where('user_id', $userId)
                ->whereDate('date', $row['date'])
                ->first() ?? new Attendance([
                    'user_id' => $userId,
                    'date' => $row['date'],
                    'status' => AttendanceStatus::Present,
                ]);

            // Hitech is the authoritative correction pass — overwrite only the
            // fields it actually reports; a blank cell (e.g. not yet punched
            // out when the export was taken) never erases a value we already have.
            if ($row['entry']) {
                $attendance->check_in_at = Carbon::createFromFormat('Y-m-d H:i:s', $row['date'].' '.$row['entry'], $tz)->utc();
            }
            if ($row['exit']) {
                $attendance->check_out_at = Carbon::createFromFormat('Y-m-d H:i:s', $row['date'].' '.$row['exit'], $tz)->utc();
            }

            $attendance->save();
            $imported++;
        }

        session()->forget(['hitech_import_rows', 'hitech_import_user_id']);
        session()->flash('status', "Imported {$imported} day(s) of attendance from the Hitech export.");
        $this->redirect(route('attendance.index', ['user_id' => $userId]), navigate: false);
    }

    public function startOver(): void
    {
        session()->forget(['hitech_import_rows', 'hitech_import_user_id']);
        $this->reset(['step', 'file', 'preview']);
        $this->step = 1;
    }

    public function render()
    {
        return view('livewire.hitech-attendance-import');
    }
}
