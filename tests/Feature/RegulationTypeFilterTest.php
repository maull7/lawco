<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegulationTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_regulation_types_by_sector(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $banking = Sector::create(['name' => 'Perbankan']);
        $energy = Sector::create(['name' => 'Energi']);
        $bankingCategory = RegulationCategory::create(['name' => 'Kredit', 'sector_id' => $banking->id]);
        $energyCategory = RegulationCategory::create(['name' => 'Pertambangan', 'sector_id' => $energy->id]);
        $type = RegulationType::create(['name' => 'Peraturan', 'level' => 1]);
        $otherType = RegulationType::create(['name' => 'Undang-Undang', 'level' => 2]);

        $this->createRegulation($type, $bankingCategory, 'Jenis Perbankan');
        $this->createRegulation($otherType, $energyCategory, 'Jenis Energi');

        $this->actingAs($admin)
            ->get(route('regulation-types.index', ['sector_id' => $banking->id]))
            ->assertOk()
            ->assertSee('Peraturan')
            ->assertDontSee('Undang-Undang')
            ->assertSee('Perbankan');
    }

    private function createRegulation(RegulationType $type, RegulationCategory $category, string $title): Regulation
    {
        return Regulation::create([
            'regulation_number' => 'REG-'.fake()->unique()->numerify('####'),
            'title' => $title,
            'regulation_type_id' => $type->id,
            'category_id' => $category->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ]);
    }
}
