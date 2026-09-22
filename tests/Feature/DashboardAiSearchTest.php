<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationType;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
