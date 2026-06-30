<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BiometricWebhookController extends Controller
{
    /**
     * Device ping / registration (ADMS GET handshake).
     * The device calls this periodically to confirm the server is alive.
     */
    public function ping(Request $request): Response
    {
        $sn = $request->query('SN', '');

        return response("GET OPTION FROM:{$sn}\nATTSTAMP=9999\n", 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Attendance log push (ADMS POST).
     * Body is plain-text tab-separated records, one per line:
     *   <UserID>\t<YYYY-MM-DD HH:MM:SS>\t<Status>\t...
     * Status: 0 = entry (check-in), 1 = exit (check-out).
     * Device local time is assumed to be Asia/Kolkata.
     */
    public function push(Request $request): Response
    {
        $punches = $this->parseBody($request->getContent());
        $processed = 0;

        foreach ($punches as $punch) {
            try {
                $user = User::where('device_user_id', $punch['device_user_id'])->first();

                if (! $user) {
                    Log::warning('Biometric: punch from unmapped device user', [
                        'device_user_id' => $punch['device_user_id'],
                        'datetime' => $punch['datetime'],
                    ]);

                    continue;
                }

                $time = Carbon::createFromFormat('Y-m-d H:i:s', $punch['datetime'], 'Asia/Kolkata');
                $date = $time->format('Y-m-d');

                // Use whereDate() so the lookup works regardless of whether the
                // Eloquent date cast stored '2026-06-30' or '2026-06-30 00:00:00'.
                $row = DB::table('attendances')
                    ->where('user_id', $user->id)
                    ->whereDate('date', $date)
                    ->first();

                if ($row) {
                    $attendance = Attendance::find($row->id);
                } else {
                    $attendance = new Attendance([
                        'user_id' => $user->id,
                        'date' => $date,
                        'status' => AttendanceStatus::Present,
                    ]);
                }

                if ($punch['status'] === 0) {
                    // Entry — keep the earliest check-in of the day
                    $asUtc = $time->utc();
                    if (is_null($attendance->check_in_at) || $asUtc->lt($attendance->check_in_at)) {
                        $attendance->check_in_at = $asUtc;
                    }
                } else {
                    // Exit — keep the latest check-out of the day
                    $asUtc = $time->utc();
                    if (is_null($attendance->check_out_at) || $asUtc->gt($attendance->check_out_at)) {
                        $attendance->check_out_at = $asUtc;
                    }
                }

                $attendance->save();
                $processed++;
            } catch (\Throwable $e) {
                Log::warning('Biometric: failed to process punch', [
                    'punch' => $punch,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response("OK: {$processed}", 200)
            ->header('Content-Type', 'text/plain');
    }

    /** Parse the raw ADMS body into an array of punch records. */
    private function parseBody(string $body): array
    {
        $punches = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);

            if ($line === '' || strtoupper($line) === 'ATTLOG') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 3) {
                continue;
            }

            $deviceUserId = trim($parts[0]);
            $datetime = trim($parts[1]);
            $status = (int) trim($parts[2]);

            if ($deviceUserId === '' || $datetime === '') {
                continue;
            }

            $punches[] = [
                'device_user_id' => $deviceUserId,
                'datetime' => $datetime,
                'status' => $status,
            ];
        }

        return $punches;
    }
}
