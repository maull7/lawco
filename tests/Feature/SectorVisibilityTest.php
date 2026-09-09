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

class SectorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_visibility_without_changing_active_status(): void
    {
        $sector = Sector::create(['name' => 'Sektor Internal']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('sectors.visibility', $sector))->assertRedirect(route('sectors.index'));
        $this->assertFalse($sector->fresh()->is_public);
        $this->assertTrue($sector->fresh()->is_active);
        $this->get(route('sectors.index'))->assertOk()->assertSee('Non Publik');
        $this->patch(route('sectors.visibility', $sector))->assertRedirect();
        $this->assertTrue($sector->fresh()->is_public);
    }

    public function test_user_cannot_change_visibility(): void
    {
        $sector = Sector::create(['name' => 'Publik']);
        $this->actingAs(User::factory()->create())
            ->patch(route('sectors.visibility', $sector))->assertForbidden();
        $this->assertTrue($sector->fresh()->is_public);
    }

    public function test_private_sector_content_is_hidden_from_guests_and_ordinary_users(): void
    {
        $public = Sector::create(['name' => 'Sektor Terbuka']);
        $private = Sector::create(['name' => 'Sektor Rahasia', 'is_public' => false]);
        RegulationCategory::create(['name' => 'Kategori Terbuka', 'sector_id' => $public->id]);
        $category = RegulationCategory::create(['name' => 'Kategori Rahasia', 'sector_id' => $private->id]);
        $subCategory = SubCategory::create(['name' => 'Sub Rahasia', 'category_id' => $category->id]);
        $regulation = Regulation::create([
            'regulation_number' => 'RAHASIA-2026', 'title' => 'Regulasi Rahasia',
            'category_id' => $category->id,
            'regulation_type_id' => RegulationType::create(['name' => 'POJK', 'level' => 1])->id,
            'year' => 2026, 'file_path' => 'regulations/test.pdf',
        ]);
        $this->get(route('index-dash'))->assertOk()->assertDontSee('Regulasi Rahasia')->assertSee('Sektor Terbuka')->assertDontSee('Sektor Rahasia');
        $this->actingAs(User::factory()->create())
            ->get(route('user.regulation-categories.index'))->assertOk()
            ->assertSee('Sektor Terbuka')->assertDontSee('Sektor Rahasia')->assertDontSee('Kategori Rahasia');
        $this->get(route('regulations.index'))->assertOk()->assertDontSee('Regulasi Rahasia');
        $this->get(route('user.regulation-categories.show', $category))->assertNotFound();
        $this->get(route('user.sub-categories.show', $subCategory))->assertNotFound();
        $this->get(route('regulations.show', $regulation))->assertNotFound();
        $this->get(route('regulations.file-raw', $regulation))->assertNotFound();
        $this->get(route('review-documents.create'))->assertOk()->assertDontSee('Regulasi Rahasia');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index'))->assertOk()->assertSee('Regulasi Rahasia');
        $this->get(route('sectors.index'))->assertOk()->assertSee('Sektor Rahasia');
        $this->assertTrue(Sector::whereKey($private->id)->exists());
    }
}
