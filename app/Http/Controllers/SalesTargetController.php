<?php

namespace App\Http\Controllers;

use App\Enums\TargetPeriodType;
use App\Http\Requests\SalesTargetRequest;
use App\Models\SalesTarget;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;

/**
 * Admin/manager-only target setting for the Sales Dashboard. A blank field
 * leaves the existing target untouched (same "blank never erases" rule used
 * by the Hitech attendance import) — this form is re-submitted as a whole
 * every time the leaderboard is edited, so silently zeroing every other
 * rep's target on each save would be a bad surprise.
 */
class SalesTargetController extends Controller
{
    public function store(SalesTargetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $createdBy = $request->user()->id;

        if (($validated['company_monthly_target'] ?? null) !== null) {
            $this->setTarget(null, TargetPeriodType::Month, $validated['company_monthly_target'], $createdBy);
        }

        if (($validated['company_fy_target'] ?? null) !== null) {
            $this->setTarget(null, TargetPeriodType::FinancialYear, $validated['company_fy_target'], $createdBy);
        }

        foreach ($validated['rep_targets'] ?? [] as $userId => $value) {
            if ($value !== null && $value !== '') {
                $this->setTarget((int) $userId, TargetPeriodType::Month, $value, $createdBy);
            }
        }

        return back()->with('status', 'Targets updated.');
    }

    private function setTarget(?int $userId, TargetPeriodType $type, mixed $rupees, int $createdBy): void
    {
        SalesTarget::updateOrCreate(
            [
                'user_id' => $userId,
                'period_type' => $type->value,
                'period_start' => $type->currentPeriodStart(),
            ],
            [
                'target_value' => Money::toPaise($rupees),
                'created_by' => $createdBy,
            ],
        );
    }
}
