<?php

namespace Database\Factories;

use App\Enums\TeamResourceCategory;
use App\Models\TeamResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamResource>
 */
class TeamResourceFactory extends Factory
{
    protected $model = TeamResource::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => null,
            'category' => null,
            'disk' => 'local',
            'path' => 'team-resources/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 5_000_000),
            'uploaded_by' => null,
        ];
    }

    public function category(TeamResourceCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
