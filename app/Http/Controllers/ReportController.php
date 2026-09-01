<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\AiUsageSettingsRequest;
use App\Models\AiUsageSetting;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use App\Models\WeeklyDigest;
use App\Services\AiUsageMetrics;
use App\Services\BusinessOverviewMetrics;
use App\Services\CollectionsMetrics;
use App\Services\LossReasonMetrics;
use App\Services\ReassignmentMetrics;
use App\Services\ReportMetrics;
use App\Services\RoleTargetMetrics;
use App\Services\SalesPipelineMetrics;
use App\Services\ScoreCalibrationMetrics;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportMetrics $metrics,
        private readonly BusinessOverviewMetrics $overview,
        private readonly CollectionsMetrics $collectionsMetrics,
        private readonly SalesPipelineMetrics $pipelineMetrics,
        private readonly AiUsageMetrics $aiUsageMetrics,
        private readonly RoleTargetMetrics $roleTargets,
        private readonly ScoreCalibrationMetrics $scoreCalibrationMetrics,
        private readonly LossReasonMetrics $lossReasonMetrics,
        private readonly ReassignmentMetrics $reassignmentMetrics,
    ) {}

    public function employeePerformance(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        $rows = $this->metrics->employeePerformanceTrend($from, $to);

        // Feeds ProductivityGapSuggestions' AI coaching call — a target gap
        // is more actionable than a percentile alone (see AiAssistant::
        // targetLine()). One batch User fetch rather than N+1 inside the
        // map, cheap either way at this company's scale but no reason not to.
        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');
        $rows = $rows->map(function (array $row) use ($users) {
            $row['target'] = $users->has($row['user_id']) ? $this->roleTargets->progressForUser($users[$row['user_id']]) : null;

            return $row;
        });

        return view('reports.employee-performance', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportEmployeePerformance(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $rows = $this->metrics->rankedEmployeePerformance($from, $to);

        return $this->csv("employee-performance-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($rows) {
            fputcsv($out, ['Employee', 'Role', 'Tasks completed', 'On-time %', 'Calls made', 'Leads converted', 'Attendance %', 'Daily reports', 'Score', 'Rank']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['user'], $r['role'], $r['tasks_completed'],
                    $r['on_time_pct'] ?? '—', $r['calls_made'], $r['leads_converted'],
                    $r['attendance_pct'] ?? '—', $r['daily_reports'],
                    $r['score'] ?? '—',
                    $r['rank'] !== null ? "{$r['rank']} of {$r['role_group_size']}" : ($r['ranking_note'] ?? '—'),
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

    public function aiUsage(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        $data = $this->aiUsageMetrics->monthly($from, $to);
        $drishti = $this->aiUsageMetrics->drishtiUsage($from, $to);
        $smdost = $this->aiUsageMetrics->smdostUsage($from, $to);
        $wadesk = $this->aiUsageMetrics->wadeskUsage($from, $to);

        return view('reports.ai-usage', [
            'data' => $data,
            'drishti' => $drishti,
            'smdost' => $smdost,
            'wadesk' => $wadesk,
            'budget' => $this->aiUsageMetrics->budgetStatus($data['estimated_cost_paise'], $drishti['estimated_cost_paise'] ?? null, $smdost['estimated_cost_paise'] ?? null, $wadesk['estimated_cost_paise'] ?? null),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function updateAiUsageSettings(AiUsageSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $setting = AiUsageSetting::current();
        $setting->update([
            'monthly_budget_paise' => Money::toPaise($validated['monthly_budget']),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Monthly AI budget updated.');
    }

    public function exportAiUsage(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $data = $this->aiUsageMetrics->monthly($from, $to);
        $drishti = $this->aiUsageMetrics->drishtiUsage($from, $to);
        $smdost = $this->aiUsageMetrics->smdostUsage($from, $to);
        $wadesk = $this->aiUsageMetrics->wadeskUsage($from, $to);

        return $this->csv("ai-usage-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($data, $drishti, $smdost, $wadesk) {
            fputcsv($out, ['Feature', 'Calls', 'Input tokens', 'Output tokens', 'Estimated cost (₹)', 'Helpful', 'Not helpful']);
            foreach ($data['by_feature'] as $r) {
                fputcsv($out, [$r['label'], $r['calls'], $r['input_tokens'], $r['output_tokens'], Money::toRupees($r['estimated_cost_paise']), $r['feedback_up'], $r['feedback_down']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Total (CRM)', $data['total_calls'], $data['total_input_tokens'], $data['total_output_tokens'], Money::toRupees($data['estimated_cost_paise']), $data['total_feedback_up'], $data['total_feedback_down']]);
            fputcsv($out, []);
            fputcsv($out, ['Cross-app', 'Calls', 'Input tokens', 'Output tokens', 'Estimated cost (₹)']);
            fputcsv($out, ['Drishti', $drishti['calls'] ?? 'n/a', $drishti['input_tokens'] ?? 'n/a', $drishti['output_tokens'] ?? 'n/a', $drishti ? Money::toRupees($drishti['estimated_cost_paise']) : 'unavailable']);
            fputcsv($out, ['SMDost', $smdost['calls'] ?? 'n/a', $smdost['input_tokens'] ?? 'n/a', $smdost['output_tokens'] ?? 'n/a', $smdost ? Money::toRupees($smdost['estimated_cost_paise']) : 'unavailable']);
            fputcsv($out, ['Wadesk', $wadesk['calls'] ?? 'n/a', $wadesk['input_tokens'] ?? 'n/a', $wadesk['output_tokens'] ?? 'n/a', $wadesk ? Money::toRupees($wadesk['estimated_cost_paise']) : 'unavailable']);
        });
    }

    public function askTheCrm(Request $request): View
    {
        $this->authorizePerformance($request);

        return view('reports.ask');
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

        $arAging = $this->overview->arAging();
        $mrr = $this->overview->mrrSnapshot();
        $concentration = $this->overview->clientConcentration($revenue['by_client'], $revenue['total']);

        // Batch-loaded once (rather than per row in the view) so the customer
        // name in AR Aging / MRR expiring / Client Concentration can link to
        // clients.show — CustomerPolicy::view needs the real model, not just
        // an id, to correctly hide a client from a Sales-restricted viewer.
        $customerIds = collect($arAging['invoices'])->pluck('customer_id')
            ->merge(collect($mrr['expiring'])->pluck('customer_id'))
            ->merge(collect($mrr['by_frequency'])->pluck('clients')->flatten(1)->pluck('customer_id'))
            ->merge(collect($concentration['clients'])->pluck('customer_id'))
            ->filter()
            ->unique();
        $customersById = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

        return view('reports.business-overview', [
            'from' => $from,
            'to' => $to,
            'showFinancialDetail' => $request->user()->hasRole(UserRole::Admin, UserRole::Accounts),
            'partners' => $this->overview->partnerPerformance(),
            'arAging' => $arAging,
            'mrr' => $mrr,
            'concentration' => $concentration,
            'pipeline' => $this->overview->pipelineFunnel($from, $to),
            'customersById' => $customersById,
        ]);
    }

    public function cashForecast(Request $request): View
    {
        $this->authorizeRevenue($request);

        return view('reports.cash-forecast', [
            'forecast' => $this->overview->cashForecast(),
            'pipelineWeighted' => $this->pipelineMetrics->kpis($request->user())['weighted_forecast'],
        ]);
    }

    /**
     * History of the Monday "Your week ahead" owner digest — the dashboard
     * only ever shows the latest one (App\Console\Commands\
     * SendWeeklyOwnerDigest overwrites the User row each run); this page is
     * the persisted trend view over WeeklyDigest rows.
     */
    public function weeklyDigests(Request $request): View
    {
        $this->authorizeWeeklyDigests($request);

        $digests = WeeklyDigest::query()->orderByDesc('digest_date')->paginate(15);

        // Oldest-first for the chart's x-axis; capped to the last 12 weeks
        // (~3 months) so the trendlines stay readable.
        $trend = WeeklyDigest::query()->orderByDesc('digest_date')->limit(12)->get()->sortBy('digest_date')->values();

        return view('reports.weekly-digests', [
            'digests' => $digests,
            'trend' => $trend,
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
                    fputcsv($out, [$i['customer'], $i['invoice_number'], $i['due_date']?->toDateString() ?? '—', $i['days_overdue'], Money::toRupees($i['balance'])]);
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
            fputcsv($out, ['By billing frequency', 'Client count', 'Total per-cycle value (₹)']);
            foreach ($mrr['by_frequency'] as $f) {
                fputcsv($out, [$f['frequency']->label(), $f['count'], Money::toRupees($f['total_cycle_value'])]);
            }
            if ($showDetail) {
                fputcsv($out, []);
                fputcsv($out, ['Recurring clients by frequency']);
                fputcsv($out, ['Frequency', 'Customer', 'Service', 'Per-cycle value (₹)', 'Next bill date']);
                foreach ($mrr['by_frequency'] as $f) {
                    foreach ($f['clients'] as $c) {
                        fputcsv($out, [$f['frequency']->label(), $c['customer'], $c['service'], Money::toRupees($c['cycle_amount']), $c['next_run_on']?->toDateString() ?? '—']);
                    }
                }
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

    /**
     * "Is the 0-100 AI score actually predictive of outcome?" -- buckets
     * closed Leads by score band and shows conversion rate + time-to-close
     * per bucket. Same access gate as Employee Performance / Loss Reasons.
     */
    public function scoreCalibration(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        return view('reports.score-calibration', [
            'data' => $this->scoreCalibrationMetrics->scoreCalibration($from, $to),
            'trend' => $this->scoreCalibrationMetrics->trend(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportScoreCalibration(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $data = $this->scoreCalibrationMetrics->scoreCalibration($from, $to);

        return $this->csv("score-calibration-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($data) {
            fputcsv($out, [
                'Score band', 'Total closed', 'Converted', 'Lost', 'Conversion %',
                'Avg days to close (converted)', 'Median days to close (converted)',
                'Avg days to close (lost)', 'Median days to close (lost)',
            ]);
            foreach ($data['buckets'] as $b) {
                fputcsv($out, [
                    $b['label'], $b['total'], $b['converted'], $b['lost'], $b['conversion_rate'],
                    $b['avg_days_to_close_converted'] ?? '—', $b['median_days_to_close_converted'] ?? '—',
                    $b['avg_days_to_close_lost'] ?? '—', $b['median_days_to_close_lost'] ?? '—',
                ]);
            }
        });
    }

    /**
     * "Loss Reasons by Rep" — deliberately not "Rep Loss Rankings": this is a
     * coaching signal (which reasons recur for which rep), not a
     * performance-ranking report. Same access gate as Employee Performance.
     */
    public function lossReasons(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        return view('reports.loss-reasons', [
            'data' => $this->lossReasonMetrics->lossReasonBreakdown($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportLossReasons(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $data = $this->lossReasonMetrics->lossReasonBreakdown($from, $to);

        return $this->csv("loss-reasons-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($data) {
            fputcsv($out, ['Overall distribution']);
            fputcsv($out, ['Reason', 'Count', '%', 'Value (₹)']);
            foreach ($data['overall'] as $r) {
                fputcsv($out, [$r['label'], $r['count'], $r['pct'], Money::toRupees($r['value'])]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Loss reasons by rep']);
            foreach ($data['by_rep'] as $rep) {
                fputcsv($out, [$rep['label'], 'Total', $rep['total']]);
                fputcsv($out, ['Reason', 'Count', '%']);
                foreach ($rep['by_reason'] as $r) {
                    fputcsv($out, [$r['label'], $r['count'], $r['pct']]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['Loss reasons by lead source']);
            foreach ($data['by_source'] as $source) {
                fputcsv($out, [$source['label'], 'Total', $source['total']]);
                fputcsv($out, ['Reason', 'Count', '%']);
                foreach ($source['by_reason'] as $r) {
                    fputcsv($out, [$r['label'], $r['count'], $r['pct']]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['Loss reasons by score band']);
            foreach ($data['by_score_band'] as $band) {
                fputcsv($out, [$band['label'], 'Total', $band['total']]);
                fputcsv($out, ['Reason', 'Count', '%']);
                foreach ($band['by_reason'] as $r) {
                    fputcsv($out, [$r['label'], $r['count'], $r['pct']]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['AI suggestion calibration']);
            fputcsv($out, ['Accepted', $data['ai_suggestion_stats']['accepted']]);
            fputcsv($out, ['Overridden', $data['ai_suggestion_stats']['overridden']]);
            fputcsv($out, ['No suggestion made', $data['ai_suggestion_stats']['no_suggestion']]);
            fputcsv($out, ['Accepted %', $data['ai_suggestion_stats']['accepted_pct']]);
        });
    }

    /**
     * "Collections & Delivery" client health, optionally scoped to one
     * referring partner or to direct (unassigned) clients via ?partner_id=.
     */
    /**
     * A management signal that's already captured (every lead handoff logs a
     * reason via App\Actions\ReassignLead) and never reported on until now.
     */
    public function reassignmentAnalytics(Request $request): View
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);

        return view('reports.reassignment-analytics', [
            'data' => $this->reassignmentMetrics->reassignmentAnalytics($from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportReassignmentAnalytics(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        [$from, $to] = $this->monthRange($request);
        $data = $this->reassignmentMetrics->reassignmentAnalytics($from, $to);

        return $this->csv("reassignment-analytics-{$from->format('Y-m-d')}_to_{$to->format('Y-m-d')}.csv", function ($out) use ($data) {
            fputcsv($out, ['Rep', 'Reassigned away', 'Reasons', 'Reassigned to']);
            foreach ($data['rows'] as $r) {
                $reasons = collect($r['reassigned_away_reasons'])->map(fn ($x) => "{$x['label']} ({$x['count']})")->implode('; ');
                fputcsv($out, [$r['user'], $r['reassigned_away_count'], $reasons ?: '—', $r['reassigned_to_count']]);
            }
        });
    }

    public function collections(Request $request): View
    {
        $this->authorizeRevenue($request);
        [$partnerId, $directOnly] = $this->partnerScope($request);

        return view('reports.collections', [
            'rows' => $this->collectionsMetrics->clientHealth($partnerId, $directOnly),
            'partners' => Partner::orderBy('name')->get(),
            'selectedPartnerId' => $request->string('partner_id')->value(),
        ]);
    }

    /**
     * Interpret ?partner_id= as: empty = all clients, "direct" = clients with
     * no referring partner, else a specific partner id.
     *
     * @return array{0: int|null, 1: bool}
     */
    private function partnerScope(Request $request): array
    {
        $raw = $request->string('partner_id')->value();

        if ($raw === 'direct') {
            return [null, true];
        }

        return [$raw !== '' ? (int) $raw : null, false];
    }

    private function authorizePerformance(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);
    }

    private function authorizeRevenue(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts), 403);
    }

    private function authorizeWeeklyDigests(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);
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
