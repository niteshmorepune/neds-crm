<?php

namespace Database\Factories;

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Models\ContentPiece;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPiece>
 */
class ContentPieceFactory extends Factory
{
    protected $model = ContentPiece::class;

    public function definition(): array
    {
        $workflow = fake()->randomElement(ContentWorkflowType::cases());

        return [
            'project_id' => Project::factory(),
            'partner_id' => null,
            'workflow_type' => $workflow,
            'platform' => fake()->randomElement(ContentPlatform::cases()),
            'status' => ContentStatus::initialFor($workflow),
            'title' => fake()->sentence(4),
            'copy_text' => null,
            'publish_date' => now()->addDays(rand(1, 30)),
            'created_by' => User::factory(),
        ];
    }

    public function agencyLed(): static
    {
        return $this->state([
            'workflow_type' => ContentWorkflowType::AgencyLed,
            'status' => ContentStatus::PendingFromAgency,
        ]);
    }

    public function nedsLed(): static
    {
        return $this->state([
            'workflow_type' => ContentWorkflowType::NedsLed,
            'status' => ContentStatus::CopyDrafting,
        ]);
    }
}
