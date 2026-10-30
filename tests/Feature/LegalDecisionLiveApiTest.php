<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
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
            ->get(route('legal-decisions.index', ['search' => 'narkotika', 'kategori' => 'pidana-umum-1', 'year' => 2026, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('is_live', true)
            ->assertSee('Putusan Nomor 123 K/PID/2026');

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://scraper.test/putusan?')
            && ! isset($request['q'])
            && $request['kategori'] === 'pidana-umum-1'
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

    public function test_already_downloaded_decision_is_marked_on_live_page(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [
                ['judul' => 'Putusan Sudah Diunduh', 'url' => $this->url, 'tahun' => 2026],
                ['judul' => 'Putusan Baru', 'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/baru.html', 'tahun' => 2026],
            ],
            'pagination' => ['current_page' => 1, 'max_page' => 1],
        ])]);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Sudah Diunduh', 'title' => 'X']);

        $response = $this->actingAs($this->admin())->get(route('legal-decisions.index'))->assertOk();

        $decisions = collect($response->viewData('decisions')->items());
        $this->assertTrue($decisions->firstWhere('decision_number', 'Putusan Sudah Diunduh')->is_downloaded);
        $this->assertFalse($decisions->firstWhere('decision_number', 'Putusan Baru')->is_downloaded);
        $response->assertSee('Sudah ada')
            ->assertDontSee(route('legal-decisions.pdf', ['url' => $this->url, 'download' => 1, 'title' => 'Putusan Sudah Diunduh']), false);
    }

    public function test_total_count_follows_filtered_total_from_scraper_api(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [['judul' => 'Putusan A', 'url' => $this->url]],
            'total_count' => 2749,
            'pagination' => ['current_page' => 1, 'max_page' => 138],
        ])]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['kategori' => 'pidana-umum-1', 'year' => 2024]))
            ->assertOk()
            ->assertViewHas('real_total_count', 2749)
            ->assertSee('2.749')
            ->assertDontSee('11.450.852');
    }

    public function test_missing_total_count_is_estimated_from_page_count(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [['judul' => 'Putusan A', 'url' => $this->url]],
            'total_count' => null,
            'pagination' => ['current_page' => 1, 'max_page' => 3],
        ])]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertViewHas('real_total_count', 60);
    }

    public function test_live_page_has_no_keyword_search_but_archive_page_does(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [['judul' => 'Putusan A', 'url' => $this->url]],
            'pagination' => ['current_page' => 1, 'max_page' => 1],
        ])]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertDontSee('Pencarian Kata Kunci')
            ->assertDontSee('name="search"', false);

        $this->actingAs($admin)->get(route('legal-decisions.index', ['kategori' => 'pidana-umum-1']))
            ->assertOk()
            ->assertSee('Filter Aktif')
            ->assertSee('Reset Semua');

        $this->actingAs($admin)->get(route('legal-decisions.downloads'))
            ->assertOk()
            ->assertSee('Pencarian Kata Kunci')
            ->assertSee('name="search"', false);
    }

    public function test_preview_source_is_cloud_only_for_decisions_archived_in_r2(): void
    {
        $other = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/lain.html';
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [
                ['judul' => 'Putusan Cloud', 'url' => $this->url],
                ['judul' => 'Putusan Tanpa R2', 'url' => $other],
                ['judul' => 'Putusan Baru', 'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/baru.html'],
            ],
            'pagination' => ['current_page' => 1, 'max_page' => 1],
        ])]);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Cloud', 'title' => 'X', 'metadata' => ['r2_path' => 'bucket/putusan_ma/a.pdf']]);
        DownloadedLegalDecision::create(['source_url' => $other, 'decision_number' => 'Putusan Tanpa R2', 'title' => 'Y', 'metadata' => ['ip' => '127.0.0.1']]);

        $response = $this->actingAs($this->admin())->get(route('legal-decisions.index'))->assertOk();

        $decisions = collect($response->viewData('decisions')->items())->keyBy('decision_number');
        $this->assertTrue($decisions['Putusan Cloud']->is_in_cloud);
        $this->assertFalse($decisions['Putusan Tanpa R2']->is_in_cloud);
        $this->assertFalse($decisions['Putusan Baru']->is_in_cloud);
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, "pdfSource: 'cloud'"));
        $this->assertSame(3, substr_count($html, "pdfSource: 'ma'"), '2 item tanpa R2 + default activeDoc di JS');
        $response->assertSee('Sumber preview: Arsip Cloud (Cloudflare R2)');
    }
}
