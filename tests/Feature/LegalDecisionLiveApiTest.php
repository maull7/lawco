<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
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

    public function test_index_shows_live_view_when_scraper_api_fails(): void
    {
        Http::fake(['scraper.test/*' => Http::response(['success' => false, 'error' => 'Gagal membuka portal MA'], 502)]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertViewHas('is_live', true)
            ->assertViewHas('scraper_error', 'Gagal membuka portal MA');
    }

    public function test_index_shows_live_view_when_scraper_api_is_unreachable(): void
    {
        Http::fake(['scraper.test/*' => Http::failedConnection()]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertViewHas('is_live', true)
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
            ->assertNotFound()
            ->assertSee('PDF putusan belum bisa ditampilkan')
            ->assertSee('Buka di Portal MA');
    }

    public function test_pdf_preview_retries_when_scraper_connection_drops(): void
    {
        Sleep::fake();
        $pdf = '%PDF-1.4'.str_repeat('x', 600);
        Http::fakeSequence('scraper.test/pdf*')
            ->pushFailedConnection()
            ->pushFailedConnection()
            ->push($pdf, 200, ['Content-Type' => 'application/pdf']);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.pdf', ['url' => $this->url]))
            ->assertOk()
            ->assertContent($pdf);
    }

    public function test_pdf_preview_shows_message_when_scraper_stays_unreachable(): void
    {
        Sleep::fake();
        Http::fake(['scraper.test/pdf*' => Http::failedConnection()]);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.pdf', ['url' => $this->url]))
            ->assertNotFound()
            ->assertSee('PDF putusan belum bisa ditampilkan');

        Http::assertSentCount(3);
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

    public function test_existing_record_whose_file_is_missing_in_r2_stays_already_downloaded_with_warning(): void
    {
        config([
            'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]);
        Storage::fake('r2');
        Storage::disk('r2')->put('putusan_ma/'.md5($this->url.'?lengkap').'.pdf', '%PDF-1.4');
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [
                ['judul' => 'Putusan Lama Tanpa PDF', 'url' => $this->url, 'tahun' => 2026],
                ['judul' => 'Putusan Lengkap', 'url' => $this->url.'?lengkap', 'tahun' => 2026],
            ],
            'pagination' => ['current_page' => 1, 'max_page' => 1],
        ])]);
        $downloader = User::factory()->create(['name' => 'Salahudin']);
        // r2_path tercatat tetapi file-nya tidak ada di R2: yang dicek adalah file sebenarnya.
        DownloadedLegalDecision::create(['user_id' => $downloader->id, 'source_url' => $this->url, 'decision_number' => 'Putusan Lama Tanpa PDF', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/hilang.pdf']]);
        DownloadedLegalDecision::create(['user_id' => $downloader->id, 'source_url' => $this->url.'?lengkap', 'decision_number' => 'Putusan Lengkap', 'title' => 'Y']);

        $response = $this->actingAs($this->admin())->get(route('legal-decisions.index'))->assertOk();

        $decisions = collect($response->viewData('decisions')->items());
        $old = $decisions->firstWhere('decision_number', 'Putusan Lama Tanpa PDF');
        $this->assertTrue($old->is_downloaded);
        $this->assertTrue($old->is_pdf_missing);
        $this->assertSame('Salahudin', $old->downloaded_by);
        $complete = $decisions->firstWhere('decision_number', 'Putusan Lengkap');
        $this->assertTrue($complete->is_downloaded);
        $this->assertFalse($complete->is_pdf_missing);
        $response->assertSeeInOrder(['Putusan Lama Tanpa PDF', 'Diunduh oleh Salahudin', 'File gagal terunduh', 'Sudah ada', 'Putusan Lengkap', 'Diunduh oleh Salahudin', 'Sudah ada']);
        $this->assertSame(1, substr_count($response->getContent(), 'File gagal terunduh'));
    }

    public function test_r2_check_is_skipped_when_r2_is_not_configured(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [['judul' => 'Putusan Sudah Diunduh', 'url' => $this->url, 'tahun' => 2026]],
            'pagination' => ['current_page' => 1, 'max_page' => 1],
        ])]);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Sudah Diunduh', 'title' => 'X']);

        $this->actingAs($this->admin())->get(route('legal-decisions.index'))
            ->assertOk()
            ->assertSee('Sudah ada')
            ->assertDontSee('File gagal terunduh');
    }

    public function test_downloader_badge_is_shown_on_live_page_for_downloaded_decision(): void
    {
        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [
                ['judul' => 'Putusan Sudah Diunduh', 'url' => $this->url, 'tahun' => 2026],
                ['judul' => 'Putusan Baru', 'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/baru.html', 'tahun' => 2026],
            ],
            'pagination' => ['current_page' => 1, 'max_page' => 1],
        ])]);
        $downloader = User::factory()->create(['name' => 'Siti Pengunduh']);
        DownloadedLegalDecision::create(['user_id' => $downloader->id, 'source_url' => $this->url, 'decision_number' => 'Putusan Sudah Diunduh', 'title' => 'X']);

        $response = $this->actingAs($this->admin())->get(route('legal-decisions.index'))->assertOk();

        $decisions = collect($response->viewData('decisions')->items());
        $this->assertSame('Siti Pengunduh', $decisions->firstWhere('decision_number', 'Putusan Sudah Diunduh')->downloaded_by);
        $this->assertNull($decisions->firstWhere('decision_number', 'Putusan Baru')->downloaded_by);
        $response->assertSeeInOrder(['Putusan Sudah Diunduh', 'Diunduh oleh Siti Pengunduh', 'Putusan Baru']);
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

    public function test_can_filter_download_status_on_live_page(): void
    {
        $downloadedUrl = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/downloaded.html';
        $notDownloadedUrl = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/not-downloaded.html';

        Http::fake(['scraper.test/putusan*' => Http::response([
            'success' => true,
            'items' => [
                ['judul' => 'Putusan Sudah Diunduh', 'url' => $downloadedUrl, 'tahun' => 2026],
                ['judul' => 'Putusan Belum Diunduh', 'url' => $notDownloadedUrl, 'tahun' => 2026],
            ],
            'pagination' => ['current_page' => 1, 'max_page' => 2],
        ])]);

        DownloadedLegalDecision::create([
            'source_url' => $downloadedUrl,
            'decision_number' => 'Putusan Sudah Diunduh',
            'title' => 'Putusan Sudah Diunduh',
        ]);

        // Default: menampilkan keduanya
        $response = $this->actingAs($this->admin())->get(route('legal-decisions.index'))->assertOk();
        $items = collect($response->viewData('decisions')->items());
        $this->assertCount(2, $items);

        // Filter: not_downloaded (hanya yang belum di-download)
        $responseNotDl = $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['download_status' => 'not_downloaded']))
            ->assertOk();
        $itemsNotDl = collect($responseNotDl->viewData('decisions')->items());
        $this->assertCount(1, $itemsNotDl);
        $this->assertSame('Putusan Belum Diunduh', $itemsNotDl->first()->decision_number);

        // Filter: downloaded (hanya yang sudah di-download)
        $responseDl = $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['download_status' => 'downloaded']))
            ->assertOk();
        $itemsDl = collect($responseDl->viewData('decisions')->items());
        $this->assertCount(1, $itemsDl);
        $this->assertSame('Putusan Sudah Diunduh', $itemsDl->first()->decision_number);
    }

    /** @return array<string, mixed> */
    private function livePage(int $page, string $url, int $maxPage = 10): array
    {
        return [
            'success' => true,
            'items' => [['judul' => 'Putusan Halaman '.$page, 'url' => $url, 'tahun' => 2026]],
            'pagination' => ['current_page' => $page, 'max_page' => $maxPage],
        ];
    }

    private function markDownloaded(string $url): void
    {
        DownloadedLegalDecision::create(['source_url' => $url, 'decision_number' => $url, 'title' => $url]);
    }

    public function test_not_downloaded_filter_skips_to_next_page_with_undownloaded_decisions(): void
    {
        $base = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/page-';
        Http::fake(function (Request $request) use ($base) {
            $page = (int) ($request['page'] ?? 1);

            return Http::response($this->livePage($page, $base.$page.'.html'));
        });
        $this->markDownloaded($base.'1.html');
        $this->markDownloaded($base.'2.html');

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['kategori' => 'perdata-khusus', 'download_status' => 'not_downloaded', 'page' => 1]))
            ->assertRedirect(route('legal-decisions.index', ['kategori' => 'perdata-khusus', 'download_status' => 'not_downloaded', 'page' => 3]))
            ->assertSessionHas('info', 'Putusan di halaman 1–2 sudah diunduh semua. Menampilkan halaman 3.');
    }

    public function test_not_downloaded_filter_stops_scanning_after_limit_and_offers_next_page(): void
    {
        $base = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/page-';
        Http::fake(function (Request $request) use ($base) {
            $page = (int) ($request['page'] ?? 1);

            return Http::response($this->livePage($page, $base.$page.'.html'));
        });
        foreach (range(1, 6) as $page) {
            $this->markDownloaded($base.$page.'.html');
        }

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['download_status' => 'not_downloaded']))
            ->assertOk()
            ->assertViewHas('download_notice', ['message' => 'Putusan di halaman 1–6 sudah diunduh semua.', 'next_page' => 7])
            ->assertSee('Cari mulai halaman 7');

        Http::assertSentCount(6);
    }

    public function test_not_downloaded_filter_reports_when_every_page_is_downloaded(): void
    {
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/only.html';
        Http::fake(['scraper.test/putusan*' => Http::response($this->livePage(1, $url, 1))]);
        $this->markDownloaded($url);

        $this->actingAs($this->admin())
            ->get(route('legal-decisions.index', ['download_status' => 'not_downloaded']))
            ->assertOk()
            ->assertViewHas('download_notice', ['message' => 'Semua putusan pada filter ini sudah diunduh.', 'next_page' => null])
            ->assertSee('Tidak ada putusan yang belum diunduh');
    }
}
