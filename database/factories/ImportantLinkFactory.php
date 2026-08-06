<?php

namespace Database\Factories;

use App\Enums\LinkDepartment;
use App\Enums\LinkPurpose;
use App\Models\ImportantLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportantLink>
 */
class ImportantLinkFactory extends Factory
{
    protected $model = ImportantLink::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'department' => null,
            'purpose' => null,
            'label' => fake()->words(2, true),
            'url' => fake()->url(),
            'sort_order' => 0,
            'created_by' => null,
        ];
    }

    public function department(LinkDepartment $department): static
    {
        return $this->state(fn () => ['department' => $department]);
    }

    public function purpose(LinkPurpose $purpose): static
    {
        return $this->state(fn () => ['purpose' => $purpose]);
    }
}
