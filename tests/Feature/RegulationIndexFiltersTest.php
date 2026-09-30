<?php

namespace Tests\Feature;

use App\Models\Regulation;
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

    public function test_date_range_is_inclusive_and_database_total_is_independent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $type = RegulationType::create(['name' => 'Peraturan', 'level' => 1]);
        foreach (['2026-01-01', '2026-01-31', '2026-02-01', null] as $index => $date) {
            Regulation::create([
                'regulation_number' => 'REG-'.$index, 'title' => 'Regulasi '.$index,
                'regulation_type_id' => $type->id, 'year' => 2026,
                'file_path' => 'test.pdf', 'tanggal_diundangkan' => $date,
            ]);
        }

        $this->actingAs($admin)->get(route('regulations.index', [
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        ]))->assertOk()->assertViewHas('totalRegulations', 4)
            ->assertViewHas('regulations', fn ($rows) => $rows->total() === 2 && $rows->count() === 2);

        $this->get(route('regulations.index', ['start_date' => '2026-02-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 1);
        $this->get(route('regulations.index', ['end_date' => '2026-01-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 1);
        $this->get(route('regulations.index', ['start_date' => '2027-01-01']))
            ->assertOk()->assertViewHas('regulations', fn ($rows) => $rows->total() === 0);
        $this->get(route('regulations.index', ['start_date' => '2026-02-01', 'end_date' => '2026-01-01']))
            ->assertSessionHasErrors('end_date');
        $this->get(route('regulations.index', ['start_date' => 'invalid']))
            ->assertSessionHasErrors('start_date');
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
