<?php

namespace App\Livewire;

use App\Services\ManagerCalendarMetrics;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manager panel doc's "Manager Calendar" (Tier 3). Full-page component
 * (routes/web.php: manager-calendar.index), admin/manager only, purely via
 * menu.access:manager-calendar — same convention as
 * ClientRadarController/ManagerActionCenterController (no Policy class, no
 * inline role check; see EmployeeProfileController's note on why a
 * redundant check would be pointless here too).
 */
#[Layout('layouts.app')]
class ManagerCalendar extends Component
{
    public string $month;

    /** @var list<string> */
    public array $activeTypes = ['meeting', 'task', 'project', 'leave'];

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonthNoOverflow()->format('Y-m');
    }

    public function goToToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function toggleType(string $type): void
    {
        abort_unless(in_array($type, ['meeting', 'task', 'project', 'leave'], true), 422);

        if (in_array($type, $this->activeTypes, true)) {
            $this->activeTypes = array_values(array_diff($this->activeTypes, [$type]));
        } else {
            $this->activeTypes[] = $type;
        }
    }

    public function render(ManagerCalendarMetrics $metrics)
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $eventsByDate = $metrics->eventsBetween($gridStart, $gridEnd)
            ->filter(fn (array $event) => in_array($event['type'], $this->activeTypes, true))
            ->groupBy('date');

        $days = [];
        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => $cursor->day,
                'inMonth' => $cursor->month === $monthStart->month,
                'isToday' => $cursor->isToday(),
                'events' => $eventsByDate->get($cursor->toDateString(), collect()),
            ];
        }

        return view('livewire.manager-calendar', [
            'days' => $days,
            'monthLabel' => $monthStart->format('F Y'),
        ]);
    }
}
