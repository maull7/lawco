<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationType;
use App\Models\User;
use App\Services\AiClient;
use App\Services\AiService;
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
}
