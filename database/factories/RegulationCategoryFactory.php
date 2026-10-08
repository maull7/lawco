<?php

namespace Database\Factories;

use App\Models\RegulationCategory;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegulationCategory>
 */
class RegulationCategoryFactory extends Factory
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
            'sector_id' => Sector::factory(),
        ];
    }
}
