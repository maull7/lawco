<?php

namespace Database\Factories;

use App\Models\Regulation;
use App\Models\RegulationText;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegulationText> */
class RegulationTextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'regulation_id' => Regulation::factory(),
            'parsed_text' => fake()->paragraphs(3, true),
        ];
    }
}
