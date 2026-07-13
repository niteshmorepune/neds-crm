<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\BusinessOverviewMetrics;
use App\Services\ReportMetrics;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportMetrics $metrics,
        private readonly BusinessOverviewMetrics $overview,
    ) {}

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

    public function leadSources(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        return view('reports.lead-sources', [
            'data' => $this->metrics->leadSourcePerformance($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportLeadSources(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $data = $this->metrics->leadSourcePerformance($from, $to);

        return $this->csv("lead-sources-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($data) {
            fputcsv($out, ['Source', 'Leads', 'Converted', 'Conversion %', 'Won value (₹)', 'Avg AI score']);
            foreach ($data['by_source'] as $r) {
                fputcsv($out, [$r['label'], $r['total'], $r['converted'], $r['conversion_rate'], Money::toRupees($r['won_value']), $r['avg_score'] ?? '—']);
            }
            fputcsv($out, []);
            fputcsv($out, ['Campaign (source / medium / campaign)', 'Leads', 'Converted', 'Conversion %', 'Won value (₹)', 'Avg AI score']);
            foreach ($data['by_campaign'] as $r) {
                fputcsv($out, [$r['label'], $r['total'], $r['converted'], $r['conversion_rate'], Money::toRupees($r['won_value']), $r['avg_score'] ?? '—']);
            }
        });
    }

    public function businessOverview(Request $request): View
    {
        $this->authorizeRevenue($request);
        [$from, $to] = $this->financialYearRange($request);
        $revenue = $this->metrics->revenue($from, $to);

        return view('reports.business-overview', [
            'from' => $from,
            'to' => $to,
            'showFinancialDetail' => $request->user()->hasRole(UserRole::Admin, UserRole::Accounts),
            'partners' => $this->overview->partnerPerformance(),
            'arAging' => $this->overview->arAging(),
            'mrr' => $this->overview->mrrSnapshot(),
            'concentration' => $this->overview->clientConcentration($revenue['by_client'], $revenue['total']),
            'pipeline' => $this->overview->pipelineFunnel($from, $to),
        ]);
    }

    public function exportBusinessOverview(Request $request): StreamedResponse
    {
        $this->authorizeRevenue($request);
        [$from, $to] = $this->financialYearRange($request);
        $revenue = $this->metrics->revenue($from, $to);
        $showDetail = $request->user()->hasRole(UserRole::Admin, UserRole::Accounts);

        $partners = $this->overview->partnerPerformance();
        $arAging = $this->overview->arAging();
        $mrr = $this->overview->mrrSnapshot();
        $concentration = $this->overview->clientConcentration($revenue['by_client'], $revenue['total']);
        $pipeline = $this->overview->pipelineFunnel($from, $to);

        return $this->csv("business-overview-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($partners, $arAging, $mrr, $concentration, $pipeline, $showDetail) {
            fputcsv($out, ['Partner Performance']);
            fputcsv($out, ['Partner', 'Referred clients', 'Active', 'Inactive', 'Won (count)', 'Won value (₹)', 'Pipeline (count)', 'Pipeline value (₹)', 'Lost (count)', 'Lost value (₹)']);
            foreach ($partners as $p) {
                fputcsv($out, [
                    $p['partner'], $p['customers_referred'], $p['customers_active'], $p['customers_inactive'],
                    $p['deals_won_count'], Money::toRupees($p['deals_won_value']),
                    $p['deals_pipeline_count'], Money::toRupees($p['deals_pipeline_value']),
                    $p['deals_lost_count'], Money::toRupees($p['deals_lost_value']),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['AR Aging']);
            if ($showDetail) {
                fputcsv($out, ['Bucket', 'Total (₹)']);
                foreach ($arAging['buckets'] as $b) {
                    fputcsv($out, [$b['label'], Money::toRupees($b['total'])]);
                }
                fputcsv($out, []);
                fputcsv($out, ['Overdue invoices']);
                fputcsv($out, ['Customer', 'Invoice #', 'Due date', 'Days overdue', 'Balance (₹)']);
                foreach ($arAging['invoices'] as $i) {
                    fputcsv($out, [$i['customer'], $i['invoice_number'], $i['due_date']->toDateString(), $i['days_overdue'], Money::toRupees($i['balance'])]);
                }
            } else {
                fputcsv($out, ['Total outstanding (₹)']);
                fputcsv($out, [Money::toRupees($arAging['total_outstanding'])]);
            }
            fputcsv($out, []);

            fputcsv($out, ['MRR / Recurring Snapshot']);
            fputcsv($out, ['Total MRR (₹)', Money::toRupees($mrr['total_mrr'])]);
            fputcsv($out, []);
            fputcsv($out, ['By service', 'Monthly equivalent (₹)']);
            foreach ($mrr['by_service'] as $s) {
                fputcsv($out, [$s['name'], Money::toRupees($s['monthly_equivalent'])]);
            }
            fputcsv($out, []);
            if ($showDetail) {
                fputcsv($out, ['Contracts expiring within 30 days']);
                fputcsv($out, ['Customer', 'Service', 'End date', 'Monthly amount (₹)']);
                foreach ($mrr['expiring'] as $e) {
                    fputcsv($out, [$e['customer'], $e['service'], $e['end_date']->toDateString(), Money::toRupees($e['monthly_equivalent'])]);
                }
            } else {
                fputcsv($out, ['Contracts expiring within 30 days (count)', $mrr['expiring_count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Client Concentration']);
            fputcsv($out, ['Top 5 clients % of revenue', $concentration['top5_pct']]);
            fputcsv($out, ['Top 10 clients % of revenue', $concentration['top10_pct']]);
            if ($showDetail) {
                fputcsv($out, []);
                fputcsv($out, ['By client', 'Total (₹)']);
                foreach ($concentration['clients'] as $c) {
                    fputcsv($out, [$c['name'], Money::toRupees($c['total'])]);
                }
            }
            fputcsv($out, []);

            fputcsv($out, ['Pipeline & Funnel']);
            fputcsv($out, ['Stage', 'Deals', 'Value (₹)']);
            foreach ($pipeline['pipeline'] as $row) {
                fputcsv($out, [$row['stage'], $row['deals'], Money::toRupees($row['value'])]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Win rate %', $pipeline['win_rate_pct'] ?? '—']);
            fputcsv($out, ['Avg deal size (₹)', $pipeline['avg_deal_size'] !== null ? Money::toRupees($pipeline['avg_deal_size']) : '—']);
            fputcsv($out, ['Avg sales cycle (days)', $pipeline['avg_sales_cycle_days'] ?? '—']);
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
