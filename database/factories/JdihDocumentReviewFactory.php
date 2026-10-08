<?php

namespace Database\Factories;

use App\Models\JdihDocumentReview;
use App\Models\RegulationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JdihDocumentReview>
 */
class JdihDocumentReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => fake()->unique()->slug(2),
            'document_id' => (string) fake()->unique()->numberBetween(1, 1000000),
            'regulation_type_id' => RegulationType::factory(),
            'reviewed_by' => User::factory(),
        ];
    }
}
