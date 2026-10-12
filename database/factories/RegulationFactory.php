<?php

namespace Database\Factories;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Regulation> */
class RegulationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'regulation_number' => fake()->unique()->numerify('REG/####'),
            'title' => fake()->sentence(),
            'regulation_type_id' => RegulationType::factory(),
            'category_id' => RegulationCategory::factory(),
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ];
    }
}
