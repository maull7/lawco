<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationRelatedReference;
use App\Models\RegulationType;
use App\Models\User;
use App\Services\AiClient;
use App\Services\AiService;
use App\Services\HermesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardAiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('index-dash.search', ['q' => 'perbankan']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_ai_matched_regulations(): void
    {
        $regulation = Regulation::create([
            'regulation_number' => 'POJK-1/2026',
            'title' => 'Regulasi Perbankan Uji',
            'regulation_type_id' => RegulationType::create(['name' => 'POJK', 'level' => 1])->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ]);

        $this->mock(AiService::class, function ($mock) use ($regulation) {
            $mock->shouldReceive('searchRegulations')
                ->once()
                ->andReturn(collect([
                    ['regulation' => $regulation, 'reason' => 'ALASAN-UNIK-123'],
                ]));
        });

        $this->actingAs(User::factory()->create())
            ->get(route('index-dash.search', ['q' => 'perbankan']))
            ->assertOk()
            ->assertSee('Alasan Relevansi')
            ->assertSee('ALASAN-UNIK-123');
    }

    public function test_query_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('index-dash.search'))
            ->assertSessionHasErrors('q');
    }

    public function test_catalog_query_skips_heavy_columns(): void
    {
        $regulation = Regulation::create([
            'regulation_number' => 'POJK-2/2026',
            'title' => 'Regulasi Perbankan Berat',
            'regulation_type_id' => RegulationType::create(['name' => 'POJK', 'level' => 1])->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
            'parsed_text' => str_repeat('isi ', 1000),
        ]);

        Regulation::create([
            'regulation_number' => 'PP-9/2020',
            'title' => 'Regulasi Kehutanan',
            'regulation_type_id' => $regulation->regulation_type_id,
            'year' => 2020,
            'file_path' => 'regulations/other.pdf',
        ]);

        $this->mock(AiClient::class, function ($mock) use ($regulation) {
            $mock->shouldReceive('complete')
                ->once()
                ->withArgs(fn (array $payload) => str_contains($payload['messages'][1]['content'], "{$regulation->id}|POJK-2/2026 - Regulasi Perbankan Berat (2026)")
                    && ! str_contains($payload['messages'][1]['content'], 'Kehutanan'))
                ->andReturn(['content' => json_encode([['id' => $regulation->id, 'alasan' => 'cocok']]), 'provider' => 'test', 'model' => 'test', 'total_tokens' => 0]);
        });

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $results = app(AiService::class)->searchRegulations('perbankan');

        $this->assertSame($regulation->id, $results->first()['regulation']->id);
        $this->assertStringNotContainsString('*', $queries[0]);
        $this->assertStringNotContainsString('parsed_text', $queries[0]);
    }

    public function test_search_without_usable_keywords_skips_ai(): void
    {
        Regulation::create([
            'regulation_number' => 'POJK-3/2026',
            'title' => 'Regulasi Perbankan',
            'regulation_type_id' => RegulationType::create(['name' => 'POJK', 'level' => 1])->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ]);

        $this->mock(AiClient::class, fn ($mock) => $mock->shouldNotReceive('complete'));

        $this->assertTrue(app(AiService::class)->searchRegulations('a ?')->isEmpty());
    }

    public function test_dashboard_search_uses_hermes(): void
    {
        $regulation = Regulation::create([
            'regulation_number' => 'POJK-4/2026',
            'title' => 'Regulasi Perbankan Dashboard',
            'regulation_type_id' => RegulationType::create(['name' => 'POJK', 'level' => 1])->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ]);
        RegulationRelatedReference::create(['regulation_id' => $regulation->id, 'name' => 'UU Perbankan', 'relationship' => 'diubah']);

        $this->mock(AiService::class, fn ($mock) => $mock->shouldReceive('searchRegulationsWithHermes')
            ->once()->with('aturan bank terbaru')
            ->andReturn(collect([['regulation' => $regulation, 'reason' => 'ALASAN-HERMES']])));

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('dashboard.search', ['q' => 'aturan bank terbaru']))
            ->assertOk()
            ->assertSee('ALASAN-HERMES')
            ->assertSee(route('dashboard', ['related' => $regulation->id]));
    }

    public function test_related_button_filters_related_table(): void
    {
        $typeId = RegulationType::create(['name' => 'POJK', 'level' => 1])->id;
        $first = Regulation::create(['regulation_number' => 'POJK-5/2026', 'title' => 'A', 'regulation_type_id' => $typeId, 'year' => 2026, 'file_path' => 'a.pdf']);
        $second = Regulation::create(['regulation_number' => 'POJK-6/2026', 'title' => 'B', 'regulation_type_id' => $typeId, 'year' => 2026, 'file_path' => 'b.pdf']);
        RegulationRelatedReference::create(['regulation_id' => $first->id, 'name' => 'TERKAIT-PERTAMA', 'relationship' => 'diubah']);
        RegulationRelatedReference::create(['regulation_id' => $second->id, 'name' => 'TERKAIT-KEDUA', 'relationship' => 'dicabut']);

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('dashboard', ['related' => $first->id]))
            ->assertOk()
            ->assertSee('TERKAIT-PERTAMA')
            ->assertDontSee('TERKAIT-KEDUA');
    }

    public function test_hermes_search_narrows_catalog_with_extracted_filters(): void
    {
        $typeId = RegulationType::create(['name' => 'POJK', 'level' => 1])->id;
        $match = Regulation::create(['regulation_number' => 'POJK-7/2024', 'title' => 'Perlindungan Konsumen Perbankan', 'regulation_type_id' => $typeId, 'year' => 2024, 'file_path' => 'a.pdf']);
        Regulation::create(['regulation_number' => 'POJK-8/2020', 'title' => 'Perlindungan Konsumen Asuransi', 'regulation_type_id' => $typeId, 'year' => 2020, 'file_path' => 'b.pdf']);

        $this->mock(AiClient::class, fn ($mock) => $mock->shouldNotReceive('complete'));
        $this->mock(HermesClient::class, function ($mock) use ($match) {
            $mock->shouldReceive('complete')->once()
                ->withArgs(fn (array $payload) => $payload['messages'][1]['content'] === 'tolong carikan aturan yang melindungi nasabah tahun 2024')
                ->andReturn(['content' => json_encode(['keywords' => ['konsumen', 'nasabah'], 'year' => 2024, 'type' => 'POJK']), 'provider' => 'hermes', 'model' => 'h', 'total_tokens' => 0]);
            $mock->shouldReceive('complete')->once()
                ->withArgs(fn (array $payload) => str_contains($payload['messages'][1]['content'], 'Perlindungan Konsumen Perbankan')
                    && ! str_contains($payload['messages'][1]['content'], 'Asuransi'))
                ->andReturn(['content' => json_encode([['id' => $match->id, 'alasan' => 'cocok']]), 'provider' => 'hermes', 'model' => 'h', 'total_tokens' => 0]);
        });

        $results = app(AiService::class)->searchRegulationsWithHermes('tolong carikan aturan yang melindungi nasabah tahun 2024');

        $this->assertSame([$match->id], $results->pluck('regulation.id')->all());
    }

    public function test_latest_regulations_can_be_filtered_on_dashboard_and_landing_page(): void
    {
        $typeId = RegulationType::create(['name' => 'POJK', 'level' => 1])->id;
        Regulation::create(['regulation_number' => 'POJK-9/2024', 'title' => 'FILTER-COCOK', 'regulation_type_id' => $typeId, 'year' => 2024, 'file_path' => 'a.pdf']);
        Regulation::create(['regulation_number' => 'POJK-10/2020', 'title' => 'FILTER-LAIN', 'regulation_type_id' => $typeId, 'year' => 2020, 'file_path' => 'b.pdf']);

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('dashboard', ['year' => 2024, 'type_id' => $typeId]))
            ->assertOk()
            ->assertSee('FILTER-COCOK')
            ->assertDontSee('FILTER-LAIN');

        $this->get(route('index-dash', ['search' => 'COCOK']))
            ->assertOk()
            ->assertSee('FILTER-COCOK')
            ->assertDontSee('FILTER-LAIN');
    }
}
