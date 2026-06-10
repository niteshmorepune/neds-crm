<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Dashboard self check-in / check-out for the current user's attendance today.
 */
class AttendanceWidget extends Component
{
    public function checkIn(): void
    {
        $today = $this->today();

        if ($today && $today->check_in_at) {
            return; // already checked in
        }

        Attendance::updateOrCreate(
            ['user_id' => auth()->id(), 'date' => Carbon::today()->toDateString()],
            ['check_in_at' => now(), 'status' => AttendanceStatus::Present->value],
        );
    }

    public function checkOut(): void
    {
        $today = $this->today();

        if ($today && $today->check_in_at && ! $today->check_out_at) {
            $today->update(['check_out_at' => now()]);
        }
    }

    public function render()
    {
        return view('livewire.attendance-widget', ['attendance' => $this->today()]);
    }

    private function today(): ?Attendance
    {
        return Attendance::where('user_id', auth()->id())
            ->whereDate('date', Carbon::today())
            ->first();
    }
}
