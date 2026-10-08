<?php

namespace Database\Factories;

use App\Models\RegulationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegulationType>
 */
class RegulationTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'level' => 4,
        ];
    }
}
