<?php

namespace Database\Factories;

use App\Models\JdihTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JdihTarget>
 */
class JdihTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'source' => fake()->unique()->slug(2),
            'target_url' => fake()->url(),
            'is_active' => true,
        ];
    }
}
