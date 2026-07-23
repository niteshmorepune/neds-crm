<?php

namespace App\Http\Controllers;

use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Http\Requests\IncentiveSettingsRequest;
use App\Models\IncentiveSetting;
use App\Models\IncentiveStatement;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\IncentiveCalculator;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IncentiveController extends Controller
{
    public function __construct(private readonly IncentiveCalculator $calculator) {}

    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user->hasRole(UserRole::Sales, UserRole::Admin, UserRole::Manager), 403);

        $monthStart = TargetPeriodType::Month->currentPeriodStart();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $data = [
            'isManager' => $isManager,
            'monthLabel' => $monthStart->format('F Y'),
        ];

        if ($isManager) {
            $reps = User::query()->where('is_active', true)->withAnyRole(UserRole::Sales)->orderBy('name')->get();

            $data['repEstimates'] = $reps->map(fn (User $rep) => [
                'user' => $rep,
                ...$this->calculator->estimateForUser($rep, $monthStart),
            ])->all();

            $data['companyTargetMet'] = $this->calculator->companyTargetMet($monthStart);
            $data['companySales'] = $this->calculator->companySalesForMonth($monthStart);
            $data['companyTarget'] = SalesTarget::query()
                ->forPeriod(null, TargetPeriodType::Month, $monthStart)
                ->value('target_value');
            $data['teamBonusPool'] = IncentiveSetting::current()->team_bonus_pool;
        }

        if ($user->hasRole(UserRole::Sales)) {
            $data['own'] = $this->calculator->estimateForUser($user, $monthStart);
            $data['history'] = IncentiveStatement::query()
                ->forUser($user->id)
                ->orderByDesc('period_start')
                ->limit(12)
                ->get();
        }

        return view('incentives.index', $data);
    }

    public function updateSettings(IncentiveSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $setting = IncentiveSetting::current();
        $setting->update([
            'team_bonus_pool' => Money::toPaise($validated['team_bonus_pool']),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Team bonus pool updated.');
    }
}
