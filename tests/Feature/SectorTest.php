<?php

namespace Tests\Feature;

use App\Models\RegulationCategory;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_sector(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('sectors.store'), [
                'name' => 'Perbankan',
                'description' => 'Sektor perbankan dan jasa keuangan.',
            ])
            ->assertRedirect(route('sectors.index'));

        $this->assertDatabaseHas('sectors', [
            'name' => 'Perbankan',
            'description' => 'Sektor perbankan dan jasa keuangan.',
        ]);
    }

    public function test_admin_can_update_sector(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sector = Sector::create(['name' => 'Perbankan']);

        $this->actingAs($admin)
            ->put(route('sectors.update', $sector), [
                'name' => 'Perbankan & Keuangan',
                'description' => 'Diperbarui.',
            ])
            ->assertRedirect(route('sectors.index'));

        $this->assertDatabaseHas('sectors', ['name' => 'Perbankan & Keuangan']);
    }

    public function test_admin_can_delete_sector_and_category_becomes_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sector = Sector::create(['name' => 'Energi']);
        RegulationCategory::create(['name' => 'EBT', 'sector_id' => $sector->id]);

        $this->actingAs($admin)
            ->delete(route('sectors.destroy', $sector))
            ->assertRedirect(route('sectors.index'));

        $this->assertDatabaseHas('regulation_categories', [
            'name' => 'EBT',
            'sector_id' => null,
        ]);
    }

    public function test_regular_user_cannot_access_sector_pages(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('sectors.index'))
            ->assertForbidden();
    }

    public function test_category_belongs_to_sector_relation(): void
    {
        $sector = Sector::create(['name' => 'Kesehatan']);
        $category = RegulationCategory::create(['name' => 'Farmasi', 'sector_id' => $sector->id]);

        $this->assertTrue($category->sector->is($sector));
        $this->assertCount(1, $sector->categories);
    }

    public function test_sector_detail_displays_description_categories_and_sub_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sector = Sector::create([
            'name' => 'Teknologi',
            'description' => 'Sektor teknologi dan transformasi digital.',
        ]);
        $category = RegulationCategory::create([
            'name' => 'Perlindungan Data',
            'sector_id' => $sector->id,
        ]);
        $category->subCategories()->create([
            'name' => 'Data Pribadi',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('sectors.index'))
            ->assertOk()
            ->assertSee('Sektor teknologi dan transformasi digital.')
            ->assertSee('Perlindungan Data')
            ->assertSee('Data Pribadi');
    }

    public function test_edit_sector_page_handles_missing_timestamps(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sector = Sector::create(['name' => 'Sektor Tanpa Tanggal']);
        $sector->timestamps = false;
        $sector->created_at = null;
        $sector->updated_at = null;
        $sector->save();

        $this->actingAs($admin)
            ->get(route('sectors.edit', $sector))
            ->assertOk()
            ->assertSee('Sektor Tanpa Tanggal')
            ->assertSee('Dibuat');
    }
}
