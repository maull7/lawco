<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_existing_regulations_with_combined_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $type = RegulationType::factory()->create(['name' => 'Peraturan']);
        $category = RegulationCategory::factory()->create(['name' => 'Ketenagakerjaan']);
        $match = Regulation::factory()->create(['title' => 'Perlindungan tenaga kerja', 'regulation_number' => 'REG/123', 'regulation_type_id' => $type->id, 'category_id' => $category->id]);
        Regulation::factory()->create(['title' => 'Perlindungan tenaga kerja', 'year' => 2025, 'regulation_type_id' => $type->id, 'category_id' => $category->id]);
        Regulation::factory()->create(['title' => 'Perlindungan tenaga kerja', 'category_id' => $category->id]);
        Regulation::factory()->create(['title' => 'Perlindungan tenaga kerja', 'regulation_type_id' => $type->id]);
        Regulation::factory()->create(['title' => 'Dokumen lain', 'regulation_type_id' => $type->id, 'category_id' => $category->id]);

        $this->actingAs($admin)->get(route('legal-decisions.index', ['search' => 'tenaga kerja', 'type_id' => $type->id, 'category_id' => $category->id, 'year' => 2026]))
            ->assertOk()->assertSee('Klasifikasi Peraturan')
            ->assertSee(route('regulations.show', $match))
            ->assertSee(route('regulations.file', $match))
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $match->id);

        $this->get(route('legal-decisions.index', ['search' => 'REG/123']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 1);
    }

    public function test_deleted_regulations_are_excluded_and_empty_results_are_displayed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $regulation = Regulation::factory()->create();
        $regulation->delete();

        $this->actingAs($admin)->get(route('legal-decisions.index', ['year' => 2026]))
            ->assertOk()->assertSee('Tidak ada dokumen yang sesuai')
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 0);
    }

    public function test_pagination_preserves_filters_and_orders_newest_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $type = RegulationType::factory()->create();
        $category = RegulationCategory::factory()->create();
        $rows = Regulation::factory()->count(16)->create(['title' => 'Dokumen pencarian', 'regulation_type_id' => $type->id, 'category_id' => $category->id]);

        $this->actingAs($admin)->get(route('legal-decisions.index', ['search' => 'pencarian']))
            ->assertOk()->assertSee('search=pencarian')
            ->assertViewHas('regulations', fn ($page) => $page->total() === 16 && $page->count() === 15 && $page->first()->id === $rows->last()->id);
        $this->get(route('legal-decisions.index', ['search' => 'pencarian', 'page' => 2]))
            ->assertOk()->assertViewHas('regulations', fn ($page) => $page->count() === 1);
    }

    public function test_documents_are_hidden_until_a_nonempty_filter_is_provided(): void
    {
        $regulation = Regulation::factory()->create(['title' => 'Dokumen tersembunyi']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([[], ['search' => '   ', 'type_id' => '', 'category_id' => '', 'year' => ''], ['page' => 2]] as $filters) {
            $this->get(route('legal-decisions.index', $filters))
                ->assertOk()->assertSee('pilih minimal satu filter')
                ->assertDontSee('Dokumen tersembunyi')
                ->assertDontSee('dokumen ditemukan')
                ->assertViewHas('regulations', null);
        }

        $this->get(route('legal-decisions.index', ['year' => $regulation->year]))
            ->assertOk()->assertSee('Dokumen tersembunyi');
        $this->get(route('legal-decisions.index'))
            ->assertOk()->assertDontSee('Dokumen tersembunyi');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('legal-decisions.index', ['year' => 'invalid', 'type_id' => 99999, 'category_id' => 99999, 'search' => str_repeat('a', 251)]))
            ->assertSessionHasErrors(['year', 'type_id', 'category_id', 'search']);
    }

    public function test_deleted_master_values_are_not_available_as_filters(): void
    {
        $type = RegulationType::factory()->create();
        $type->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('legal-decisions.index', ['type_id' => $type->id]))
            ->assertSessionHasErrors('type_id');
    }

    public function test_only_admin_can_access_the_page(): void
    {
        $this->get(route('legal-decisions.index'))->assertRedirect(route('login'));
        foreach (['user', 'sub_admin', 'reviewer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('legal-decisions.index'))->assertForbidden();
        }
    }

    public function test_admin_sidebar_contains_only_document_menu_under_legal_decisions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('legal-decisions.index'))->assertOk()
            ->assertSee('Keputusan Hukum')
            ->assertSee('Daftar Dokumen');

        $sidebar = view('components.sidebar')->render();
        $decisionMenu = explode('Master', explode('Keputusan Hukum</p>', $sidebar)[1])[0];
        $this->assertStringNotContainsString(route('regulation-types.index'), $decisionMenu);
        $this->assertStringNotContainsString(route('regulation-categories.index'), $decisionMenu);
    }
}
