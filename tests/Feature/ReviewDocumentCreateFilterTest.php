<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDocumentCreateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_loads_taxonomy_and_preserves_selected_regulations(): void
    {
        $sector = Sector::create(['name' => 'Keuangan', 'is_active' => true]);
        $category = RegulationCategory::create(['name' => 'Perbankan', 'sector_id' => $sector->id]);
        $subCategory = SubCategory::create(['name' => 'Bank Umum', 'category_id' => $category->id, 'is_active' => true]);
        $type = RegulationType::create(['name' => 'POJK', 'level' => 1]);
        $regulation = Regulation::create([
            'category_id' => $category->id,
            'regulation_type_id' => $type->id,
            'regulation_number' => 'POJK 1/2026',
            'title' => 'Peraturan Bank Umum',
            'year' => 2026,
            'file_path' => 'regulations/example.pdf',
        ]);
        $regulation->subCategories()->attach($subCategory);

        $this->actingAs(User::factory()->create())
            ->withSession(['_old_input' => [
                'regulation_ids' => [$regulation->id],
                'filter_sector_id' => (string) $sector->id,
                'filter_category_id' => (string) $category->id,
                'filter_sub_category_id' => (string) $subCategory->id,
            ]])
            ->get(route('review-documents.create'))
            ->assertOk()
            ->assertSeeInOrder(['>Sektor</label>', '>Kategori</label>', '>Sub Kategori</label>', 'Pilih Regulasi yang Berlaku'], false)
            ->assertSee('Keuangan')
            ->assertSee('Bank Umum')
            ->assertSee('value="'.$regulation->id.'" checked', false)
            ->assertViewHas('categories', function ($categories) use ($subCategory): bool {
                $category = $categories->first();

                return $category->relationLoaded('sector')
                    && $category->relationLoaded('subCategories')
                    && $category->regulations->first()->relationLoaded('subCategories')
                    && $category->regulations->first()->subCategories->contains($subCategory);
            });
    }

    public function test_create_page_handles_an_empty_regulation_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('review-documents.create'))
            ->assertOk()
            ->assertSee('Semua Sektor')
            ->assertSee('Semua Kategori')
            ->assertSee('Semua Sub Kategori')
            ->assertSee('Tidak ada regulasi yang sesuai dengan filter atau pencarian.');
    }

    public function test_sub_admin_cannot_access_create_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sub_admin']))
            ->get(route('review-documents.create'))
            ->assertForbidden();
    }
}
