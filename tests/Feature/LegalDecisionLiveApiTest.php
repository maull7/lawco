<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LegalDecisionLiveApiTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-live-api.html';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ma_scraper.url' => 'https://scraper.test/',
            'services.ma_scraper.token' => 'secret-token',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_shows_live_decisions_from_scraper_api(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [[
                'judul' => 'Putusan Nomor 123 K/PID/2026',
                'url' => $this->url,
                'pengadilan' => 'Mahkamah Agung',
                'klasifikasi' => 'Pidana',
                'tahun' => 2026,
                'ringkasan' => 'Ringkasan putusan',
            ]],
            'sidebar_filters' => [],
            'total_count' => 100,
            'pagination' => ['current_page' => 2, 'max_page' => 5, 'has_next' => true, 'has_prev' => true],
        ])]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['search' => 'narkotika', 'year' => 2026, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('is_live', true)
            ->assertSee('Putusan Nomor 123 K/PID/2026');

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://scraper.test/putusan?')
            && $request['q'] === 'narkotika'
            && $request['tahun'] === 2026
            && $request['page'] === 2
            && $request->hasHeader('X-API-Key', 'secret-token'));
    }

    public function test_index_falls_back_to_local_data_when_scraper_api_fails(): void
    {
        Http::fake(['scraper.test/*' => Http::response(['success' => false, 'error' => 'Gagal membuka portal MA'], 502)]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertViewHas('is_live', false)
            ->assertViewHas('scraper_error', 'Gagal membuka portal MA');
    }

    public function test_index_falls_back_when_scraper_api_is_unreachable(): void
    {
        Http::fake(['scraper.test/*' => Http::failedConnection()]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertViewHas('is_live', false)
            ->assertViewHas('scraper_error', fn (?string $error): bool => str_contains((string) $error, 'tidak dapat dihubungi'));
    }

    public function test_index_reports_missing_scraper_configuration(): void
    {
        config(['services.ma_scraper.url' => null]);
        Http::fake();

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertViewHas('scraper_error', 'API scraper MA belum dikonfigurasi (MA_SCRAPER_URL).');

        Http::assertNothingSent();
    }

    public function test_pdf_preview_is_fetched_from_scraper_api(): void
    {
        $pdf = '%PDF-1.4'.str_repeat('x', 600);
        Http::fake(['scraper.test/pdf*' => Http::response($pdf, 200, ['Content-Type' => 'application/pdf'])]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.pdf', ['url' => $this->url]))
            ->assertOk()
            ->assertContent($pdf);

        Http::assertSent(fn (Request $request): bool => $request['url'] === $this->url
            && $request->hasHeader('X-API-Key', 'secret-token'));
    }

    public function test_failed_pdf_download_redirects_to_ma_portal(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false], 502)]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.pdf', ['url' => $this->url, 'download' => 1]))
            ->assertRedirect($this->url);
    }

    public function test_failed_pdf_preview_returns_not_found(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false], 502)]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.pdf', ['url' => $this->url]))
            ->assertNotFound();
    }
}
