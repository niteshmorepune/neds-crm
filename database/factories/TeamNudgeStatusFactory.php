<?php

namespace Database\Factories;

use App\Enums\NudgeStatus;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamNudgeStatus>
 */
class TeamNudgeStatusFactory extends Factory
{
    protected $model = TeamNudgeStatus::class;

    public function definition(): array
    {
        return [
            'team_nudge_id' => TeamNudge::factory(),
            'user_id' => User::factory(),
            'period_start' => null,
            'status' => NudgeStatus::Pending->value,
            'completed_via' => null,
            'completed_at' => null,
            'snoozed_until' => null,
        ];
    }
}
