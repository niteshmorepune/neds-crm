<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'notes' => null,
        ];
    }

    /**
     * A partner with active portal access (password "password").
     */
    public function portalUser(): static
    {
        return $this->state(fn () => [
            'portal_enabled' => true,
            'password' => Hash::make('password'),
            'password_set_at' => now(),
        ]);
    }
}
