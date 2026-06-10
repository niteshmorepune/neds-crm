<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Computes ticket SLA due-times in business hours. The clock advances only
 * during configured working hours on working days (config/sla.php), in the
 * app timezone (Asia/Kolkata).
 */
class SlaCalculator
{
    public function dueAt(CarbonInterface $from, int $businessHours): Carbon
    {
        $tz = config('app.timezone');
        $workingDays = config('sla.working_days');
        $startHour = (int) config('sla.start_hour');
        $endHour = (int) config('sla.end_hour');

        $cursor = Carbon::parse($from)->setTimezone($tz);
        $remaining = $businessHours * 60; // minutes
        $guard = 0;

        while ($remaining > 0 && $guard++ < 1000) {
            // Skip non-working days entirely.
            if (! in_array($cursor->isoWeekday(), $workingDays, true)) {
                $cursor = $cursor->addDay()->setTime($startHour, 0);

                continue;
            }

            $dayStart = $cursor->copy()->setTime($startHour, 0);
            $dayEnd = $cursor->copy()->setTime($endHour, 0);

            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart;
            }

            if ($cursor->greaterThanOrEqualTo($dayEnd)) {
                $cursor = $cursor->addDay()->setTime($startHour, 0);

                continue;
            }

            $minutesLeftToday = (int) $cursor->diffInMinutes($dayEnd, true);

            if ($remaining <= $minutesLeftToday) {
                return $cursor->copy()->addMinutes($remaining);
            }

            $remaining -= $minutesLeftToday;
            $cursor = $dayEnd->copy()->addDay()->setTime($startHour, 0);
        }

        return $cursor;
    }
}
