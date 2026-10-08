<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterRegulationPaginationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string, string, string, string}> */
    public static function masters(): array
    {
        return [
            'types' => [RegulationType::class, 'regulation-types.index', 'types', 'manage_types'],
            'categories' => [RegulationCategory::class, 'regulation-categories.index', 'categories', 'manage_categories'],
        ];
    }

    #[DataProvider('masters')]
    public function test_search_is_paginated_and_retained_without_duplicate_rows(string $model, string $route, string $variable, string $permission): void
    {
        $sector = Sector::factory()->create();
        for ($index = 1; $index <= 16; $index++) {
            $model::factory()->create(array_merge(['name' => sprintf('Master Uji %02d', $index)], $model === RegulationCategory::class ? ['sector_id' => $sector->id] : []));
        }
        $model::factory()->create(['name' => 'Data Tidak Sesuai']);
        $deleted = $model::factory()->create(['name' => 'Master Uji Terhapus']);
        $deleted->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route($route, ['search' => 'Master Uji']))->assertOk()
            ->assertSee('Master Uji 01')->assertDontSee('Master Uji 16')->assertDontSee('Master Uji Terhapus')
            ->assertDontSee('Data Tidak Sesuai')->assertSee('search=Master%20Uji', false)
            ->assertViewHas($variable, fn (LengthAwarePaginator $rows): bool => $rows->total() === 16 && $rows->count() === 15);
        $this->get(route($route, ['search' => 'Master Uji', 'page' => 2]))->assertOk()
            ->assertSee('Master Uji 16')->assertDontSee('Master Uji 01')
            ->assertViewHas($variable, fn (LengthAwarePaginator $rows): bool => $rows->count() === 1 && $rows->firstItem() === 16);
        $this->get(route($route, ['search' => 'tidak ditemukan']))->assertOk()
            ->assertSee($model === RegulationType::class ? 'Tidak ada jenis regulasi yang cocok' : 'Tidak ada kategori yang cocok');
        $this->get(route($route, ['search' => '   ']))->assertOk()
            ->assertViewHas($variable, fn (LengthAwarePaginator $rows): bool => $rows->total() === 17);
    }

    #[DataProvider('masters')]
    public function test_search_treats_wildcards_literally(string $model, string $route, string $variable, string $permission): void
    {
        $model::factory()->create(['name' => 'Nama 100%_khusus!']);
        $model::factory()->create(['name' => 'Nama Biasa']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route($route, ['search' => '%_khusus!']))->assertOk()->assertSee('Nama 100%_khusus!')
            ->assertDontSee('Nama Biasa')->assertViewHas($variable, fn (LengthAwarePaginator $rows): bool => $rows->total() === 1);
    }

    public function test_category_search_by_description_combines_with_sector_and_preserves_pagination(): void
    {
        $sector = Sector::factory()->create();
        $other = Sector::factory()->create();
        $categories = RegulationCategory::factory()->count(16)->create(['sector_id' => $other->id, 'description' => 'Deskripsi kepatuhan khusus']);
        $type = RegulationType::factory()->create();
        foreach ($categories as $category) {
            Regulation::create(['title' => 'Dokumen Kategori', 'regulation_number' => (string) $category->id, 'year' => 2026,
                'regulation_type_id' => $type->id, 'category_id' => $category->id, 'sector_id' => $sector->id, 'file_path' => 'regulations/test.pdf']);
        }
        RegulationCategory::factory()->create(['name' => 'Kategori Sektor Lain', 'sector_id' => $other->id, 'description' => 'Deskripsi kepatuhan khusus']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulation-categories.index', ['search' => 'kepatuhan khusus', 'sector_id' => $sector->id]))->assertOk()
            ->assertDontSee('Kategori Sektor Lain')->assertSee('sector_id='.$sector->id)->assertSee('search=kepatuhan%20khusus', false)
            ->assertViewHas('categories', fn (LengthAwarePaginator $rows): bool => $rows->total() === 16);
    }

    public function test_category_listing_uses_counts_without_loading_regulations(): void
    {
        $category = RegulationCategory::factory()->create();
        $type = RegulationType::factory()->create();
        Regulation::create([
            'title' => 'Dokumen Kategori Uji', 'regulation_number' => '1', 'year' => 2026,
            'regulation_type_id' => $type->id, 'category_id' => $category->id, 'sector_id' => $category->sector_id,
            'file_path' => 'regulations/test.pdf',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulation-categories.index'))->assertOk()->assertSee($category->sector->name)
            ->assertViewHas('categories', function (LengthAwarePaginator $rows) use ($category): bool {
                $item = $rows->firstWhere('id', $category->id);

                return $item->regulations_count === 1 && ! $item->relationLoaded('regulations');
            });
    }

    #[DataProvider('masters')]
    public function test_index_validates_search_filters_and_preserves_permissions(string $model, string $route, string $variable, string $permission): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'sub_admin']))->get(route($route))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'sub_admin', 'permissions' => [$permission]]))
            ->get(route($route))->assertOk();
        $this->getJson(route($route, ['search' => str_repeat('a', 201), 'sector_id' => 999999, 'page' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors(['search', 'sector_id', 'page']);
        $this->getJson(route($route, ['search' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('search');
    }
}
