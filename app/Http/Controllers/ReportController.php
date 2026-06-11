<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\ReportMetrics;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportMetrics $metrics) {}

    public function employeePerformance(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        return view('reports.employee-performance', [
            'rows' => $this->metrics->employeePerformance($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportEmployeePerformance(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $rows = $this->metrics->employeePerformance($from, $to);

        return $this->csv("employee-performance-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($rows) {
            fputcsv($out, ['Employee', 'Role', 'Tasks completed', 'On-time %', 'Calls made', 'Leads converted', 'Attendance %', 'Daily reports']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['user'], $r['role'], $r['tasks_completed'],
                    $r['on_time_pct'] ?? '—', $r['calls_made'], $r['leads_converted'],
                    $r['attendance_pct'] ?? '—', $r['daily_reports'],
                ]);
            }
        });
    }

    public function revenue(Request $request): View
    {
        $this->authorizeRevenue($request);
        [$from, $to] = $this->financialYearRange($request);

        return view('reports.revenue', [
            'data' => $this->metrics->revenue($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportRevenue(Request $request): StreamedResponse
    {
        $this->authorizeRevenue($request);
        [$from, $to] = $this->financialYearRange($request);
        $data = $this->metrics->revenue($from, $to);

        return $this->csv("revenue-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($data) {
            fputcsv($out, ['Month', 'Recurring (₹)', 'One-time (₹)', 'Total (₹)']);
            foreach ($data['monthly'] as $m) {
                fputcsv($out, [$m['month'], Money::toRupees($m['recurring']), Money::toRupees($m['one_time']), Money::toRupees($m['total'])]);
            }
            fputcsv($out, []);
            fputcsv($out, ['By service', 'Total (₹)']);
            foreach ($data['by_service'] as $s) {
                fputcsv($out, [$s['name'], Money::toRupees($s['total'])]);
            }
            fputcsv($out, []);
            fputcsv($out, ['By client', 'Total (₹)']);
            foreach ($data['by_client'] as $c) {
                fputcsv($out, [$c['name'], Money::toRupees($c['total'])]);
            }
        });
    }

    private function authorizePerformance(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);
    }

    private function authorizeRevenue(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts), 403);
    }

    /**
     * Resolve the selected month (?month=YYYY-MM), default current month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(Request $request): array
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->value())->startOfMonth()
            : now()->startOfMonth();

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    /**
     * Resolve the selected financial year (?fy=YYYY = April YYYY–March YYYY+1),
     * default the current Indian FY.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function financialYearRange(Request $request): array
    {
        $startYear = $request->filled('fy')
            ? (int) $request->integer('fy')
            : (now()->month >= 4 ? now()->year : now()->year - 1);

        $from = Carbon::create($startYear, 4, 1)->startOfDay();
        $to = Carbon::create($startYear + 1, 3, 31)->endOfDay();

        return [$from, $to];
    }

    private function csv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $out = fopen('php://output', 'w');
            $writer($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
