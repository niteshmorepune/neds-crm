<?php

namespace App\Services;

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\QuarterlyAward;
use App\Support\FinancialQuarter;
use Illuminate\Support\Collection;

/**
 * Picks a candidate winner per department (the top-scored row in that role
 * group) plus one company-wide candidate (the single highest score across
 * every department) from ReportMetrics::rankedEmployeePerformance() — no new
 * metrics collected, this only decides who wins from numbers that already
 * exist. AI drafts a citation for whichever candidates actually need one,
 * then each is upserted as a Pending QuarterlyAward. A row already Approved
 * is left untouched on a re-run, so correcting underlying data and
 * regenerating can never silently overwrite a decision already made.
 */
class QuarterlyAwardGenerator
{
    public function __construct(
        private readonly ReportMetrics $metrics,
        private readonly AiAssistant $ai,
    ) {}

    /**
     * @return Collection<int, QuarterlyAward>
     */
    public function generate(string $financialYear, int $quarter): Collection
    {
        [$from, $to] = FinancialQuarter::range($financialYear, $quarter);

        $ranked = $this->metrics->rankedEmployeePerformance($from, $to)->whereNotNull('score')->values();

        if ($ranked->isEmpty()) {
            return collect();
        }

        $candidates = $ranked->groupBy('role_value')
            ->map(fn (Collection $group) => $group->sortByDesc('score')->first())
            ->all();

        $candidates[QuarterlyAward::COMPANY_WIDE] = $ranked->sortByDesc('score')->first();

        $existing = QuarterlyAward::where('financial_year', $financialYear)
            ->where('quarter', $quarter)
            ->get()
            ->keyBy('department');

        $needsCitation = collect($candidates)->filter(
            fn (array $row, string $department) => ($existing->get($department)?->status) !== AwardStatus::Approved
        );

        $citationsByDepartment = collect();
        if ($needsCitation->isNotEmpty()) {
            $forAi = $needsCitation->map(fn (array $row, string $department) => $row + [
                'department' => $department,
                'department_label' => $department === QuarterlyAward::COMPANY_WIDE
                    ? 'Company-wide'
                    : UserRole::from($department)->label(),
            ])->values();

            $citationsByDepartment = collect($this->ai->draftQuarterlyAwardCitations($forAi) ?? [])->keyBy('department');
        }

        foreach ($needsCitation as $department => $row) {
            QuarterlyAward::updateOrCreate(
                ['financial_year' => $financialYear, 'quarter' => $quarter, 'department' => $department],
                [
                    'user_id' => $row['user_id'],
                    'score' => $row['score'],
                    'citation' => $citationsByDepartment->get($department)['citation'] ?? null,
                    'status' => AwardStatus::Pending,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]
            );
        }

        return QuarterlyAward::where('financial_year', $financialYear)
            ->where('quarter', $quarter)
            ->with('user')
            ->get();
    }
}
