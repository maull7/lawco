<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use App\Repositories\RegulationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegulationIndexFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_summary_status_filter_and_excel_button_are_hidden_for_users(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('regulations.index'))
            ->assertOk()
            ->assertDontSee('Ringkasan Parse Regulasi')
            ->assertDontSee('name="parse_status"', false)
            ->assertDontSee('Download Excel')
            ->assertSee('name="search"', false)
            ->assertSee('name="search_content"', false);
    }

    public function test_parse_summary_status_filter_and_excel_button_remain_visible_for_other_roles(): void
    {
        foreach (['admin', 'sub_admin', 'reviewer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('regulations.index'))
                ->assertOk()
                ->assertSee('Ringkasan Parse Regulasi')
                ->assertSee('name="parse_status"', false)
                ->assertSee('Download Excel');
        }
    }

    public function test_category_keyword_matches_names_across_different_category_ids(): void
    {
        $categories = RegulationCategory::factory()->count(2)->create(['name' => 'Ketenagakerjaan']);
        $rows = collect($categories)->map(fn ($category) => Regulation::factory()->create(['category_id' => $category->id]));
        Regulation::factory()->create(['category_id' => RegulationCategory::factory()->create(['name' => 'Energi'])->id]);
        Regulation::factory()->create(['category_id' => null]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['category_keyword' => 'tenaga']))
            ->assertOk()->assertViewHas('regulations', fn ($results) => $results->total() === 2
                && collect($results->modelKeys())->sort()->values()->all() === $rows->pluck('id')->sort()->values()->all());
        $this->get(route('regulations.index', ['category_keyword' => 'tidak ditemukan']))
            ->assertOk()->assertViewHas('regulations', fn ($results) => $results->total() === 0);
    }

    public function test_sectors_can_be_searched_by_name_and_description(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sector = Sector::create(['name' => 'Perbankan', 'description' => 'Jasa keuangan']);
        Sector::create(['name' => 'Energi']);

        foreach (['Perbankan', 'keuangan'] as $search) {
            $this->actingAs($admin)->get(route('sectors.index', ['search' => $search]))
                ->assertOk()->assertViewHas('sectors', fn ($sectors) => $sectors->modelKeys() === [$sector->id]);
        }

        $this->get(route('sectors.index', ['search' => 'tidak ditemukan']))
            ->assertOk()->assertSee('Tidak ada sektor yang sesuai pencarian');
    }

    public function test_searchable_sector_choice_filters_regulations_and_preserves_the_selection(): void
    {
        $sector = Sector::factory()->create(['name' => 'Keuangan']);
        $other = Sector::factory()->create(['name' => 'Energi']);
        $selected = Regulation::factory()->create(['sector_id' => $sector->id]);
        Regulation::factory()->create(['sector_id' => $other->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['sector_id' => $sector->id]))->assertOk()
            ->assertViewHas('regulations', fn ($rows) => $rows->modelKeys() === [$selected->id])
            ->assertViewHas('filters', fn ($filters) => (int) $filters['sector_id'] === $sector->id)
            ->assertSee('Cari dan pilih sektor')->assertSee('Keuangan')->assertSee('Energi');
        $this->get(route('regulations.index', ['sector_id' => '']))->assertOk()
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 2);
    }

    public function test_date_range_is_inclusive_and_database_total_is_independent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $type = RegulationType::create(['name' => 'Peraturan', 'level' => 1]);
        foreach (['2026-01-01', '2026-01-31', '2026-02-01', null] as $index => $date) {
            Regulation::factory()->create([
                'regulation_number' => 'REG-'.$index, 'title' => 'Regulasi '.$index,
                'regulation_type_id' => $type->id, 'year' => 2026,
                'file_path' => 'test.pdf', 'created_at' => $date ?? '2025-12-31',
            ]);
        }

        $this->actingAs($admin)->get(route('regulations.index', [
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        ]))->assertOk()->assertViewHas('totalRegulations', 4)
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 2 && $rows->count() === 2);

        $this->get(route('regulations.index', ['start_date' => '2026-02-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 1);
        $this->get(route('regulations.index', ['end_date' => '2026-01-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 2);
        $this->get(route('regulations.index', ['start_date' => '2027-01-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 0);
        $this->get(route('regulations.index', ['start_date' => '2026-02-01', 'end_date' => '2026-01-01']))
            ->assertSessionHasErrors('end_date');
        $this->get(route('regulations.index', ['start_date' => 'invalid']))
            ->assertSessionHasErrors('start_date');
    }

    public function test_default_date_filter_uses_date_added_including_the_entire_final_day(): void
    {
        foreach (['2026-01-01 00:00:00', '2026-01-31 23:59:59', '2026-02-01 00:00:00'] as $date) {
            Regulation::factory()->create(['created_at' => $date, 'tanggal_diundangkan' => null]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['start_date' => '2026-01-01', 'end_date' => '2026-01-31']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 2);
        $this->get(route('regulations.index', ['end_date' => '2026-01-31']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 2);
        $this->get(route('regulations.index', ['start_date' => '2026-02-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 1);
    }

    public function test_date_filter_always_uses_created_at_even_with_a_legacy_date_field_parameter(): void
    {
        $included = Regulation::factory()->create(['created_at' => '2026-01-15 23:59:59', 'tanggal_tetapkan' => '2026-02-01']);
        Regulation::factory()->create(['created_at' => '2026-02-01 00:00:00', 'tanggal_tetapkan' => '2026-01-15']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['date_field' => 'tanggal_tetapkan', 'start_date' => '2026-01-15', 'end_date' => '2026-01-15']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->modelKeys() === [$included->id]);
    }

    public function test_parse_status_filter_handles_each_status_and_legacy_unparsed_rows(): void
    {
        foreach (['not_parsed', 'complete', 'incomplete', 'parsing', 'failed', ''] as $status) {
            Regulation::factory()->create(['parse_status' => $status]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['not_parsed' => 2, 'complete' => 1, 'incomplete' => 1, 'parsing' => 1, 'failed' => 1] as $status => $count) {
            $this->get(route('regulations.index', ['parse_status' => $status]))->assertOk()
                ->assertViewHas('regulations', fn ($rows) => $rows->total() === $count);
        }
        $this->get(route('regulations.index', ['parse_status' => '']))->assertOk()
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 6);
        $this->get(route('regulations.index', ['parse_status' => 'invalid']))->assertSessionHasErrors('parse_status');
    }

    public function test_parse_status_and_date_filters_combine(): void
    {
        $selected = Regulation::factory()->create(['parse_status' => 'complete', 'created_at' => '2026-01-15 10:00:00']);
        Regulation::factory()->create(['parse_status' => 'failed', 'created_at' => '2026-01-15 10:00:00']);
        Regulation::factory()->create(['parse_status' => 'complete', 'created_at' => '2026-02-15 10:00:00']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['parse_status' => 'complete', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->modelKeys() === [$selected->id]);
    }

    public function test_parse_summary_counts_all_statuses_independently_of_filters(): void
    {
        foreach (['complete', 'complete', 'not_parsed', 'not_parsed', 'incomplete', 'parsing', 'failed'] as $status) {
            Regulation::factory()->create(['parse_status' => $status]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['search' => 'no-matching-regulation']))
            ->assertOk()
            ->assertViewHas('totalRegulations', 7)
            ->assertViewHas('parseSummary', [
                'complete' => 2, 'remaining' => 5, 'not_parsed' => 2,
                'incomplete' => 1, 'parsing' => 1, 'failed' => 1, 'percent' => 28.6,
            ])
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 0)
            ->assertSee('2 / 7')
            ->assertSee('28,6% berstatus complete');
    }

    public function test_parse_summary_handles_an_empty_database(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index'))
            ->assertOk()
            ->assertViewHas('totalRegulations', 0)
            ->assertViewHas('parseSummary', [
                'complete' => 0, 'remaining' => 0, 'not_parsed' => 0,
                'incomplete' => 0, 'parsing' => 0, 'failed' => 0, 'percent' => 0,
            ])
            ->assertSee('0 / 0');
    }

    public function test_pagination_enforces_ten_thousand_result_limit(): void
    {
        $type = RegulationType::create(['name' => 'Peraturan', 'level' => 1]);
        $row = ['regulation_number' => 'REG', 'title' => 'Regulasi', 'regulation_type_id' => $type->id,
            'year' => 2026, 'file_path' => 'test.pdf'];
        for ($batch = 0; $batch < 20; $batch++) {
            DB::table('regulations')->insert(array_fill(0, 500, $row));
        }
        DB::table('regulations')->insert($row);
        $repository = app(RegulationRepository::class);
        $this->app['request']->query->set('page', 667);
        $results = $repository->paginateWithFilters([]);
        $this->assertSame(10000, $results->total());
        $this->assertCount(10, $results);
        $this->app['request']->query->set('page', 668);
        $this->assertCount(0, $repository->paginateWithFilters([]));
        $this->assertSame(10001, Regulation::count());
    }
}
