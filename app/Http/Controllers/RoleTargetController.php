<?php

namespace App\Http\Controllers;

use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Http\Requests\RoleTargetRequest;
use App\Models\RoleTarget;
use App\Models\User;
use App\Services\RoleTargetMetrics;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/manager target-setting for the 4 non-Sales KRA metrics (Support,
 * Accounts, Intern, Telecaller — see App\Enums\TargetMetric), mirroring
 * SalesTargetController's shape. Sales keeps its own dedicated page/
 * controller unchanged.
 */
class RoleTargetController extends Controller
{
    private const ROLES = [UserRole::Support, UserRole::Accounts, UserRole::Intern, UserRole::Telecaller];

    public function index(RoleTargetMetrics $metrics): View
    {
        $sections = collect(self::ROLES)->map(fn (UserRole $role) => array_merge(
            ['role' => $role],
            $metrics->teamRows($role),
        ));

        return view('role-targets.index', ['sections' => $sections]);
    }

    public function store(RoleTargetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $createdBy = $request->user()->id;

        foreach ($validated['role_wide_targets'] ?? [] as $roleValue => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $role = UserRole::tryFrom($roleValue);
            $metric = $role ? TargetMetric::forRole($role) : null;
            if ($metric !== null) {
                $this->setTarget(null, $metric, $value, $createdBy);
            }
        }

        if (! empty($validated['rep_targets'])) {
            $users = User::whereIn('id', array_keys($validated['rep_targets']))->get()->keyBy('id');

            foreach ($validated['rep_targets'] as $userId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $metric = TargetMetric::forRole($users->get($userId)?->role ?? UserRole::Admin);
                if ($metric !== null) {
                    $this->setTarget((int) $userId, $metric, $value, $createdBy);
                }
            }
        }

        return back()->with('status', 'Targets updated.');
    }

    private function setTarget(?int $userId, TargetMetric $metric, mixed $value, int $createdBy): void
    {
        RoleTarget::updateOrCreate(
            [
                'user_id' => $userId,
                'metric' => $metric->value,
                'period_type' => TargetPeriodType::Month->value,
                'period_start' => TargetPeriodType::Month->currentPeriodStart(),
            ],
            [
                'target_value' => $metric->isMoney() ? Money::toPaise($value) : (int) $value,
                'created_by' => $createdBy,
            ],
        );
    }
}
