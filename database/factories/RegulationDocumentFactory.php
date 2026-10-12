<?php

namespace Database\Factories;

use App\Models\Regulation;
use App\Models\RegulationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegulationDocument> */
class RegulationDocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'regulation_id' => Regulation::factory(),
            'name' => fake()->words(3, true),
            'document_type' => 'lampiran',
            'file_path' => 'regulation-documents/test.pdf',
        ];
    }
}
