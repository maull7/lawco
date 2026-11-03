<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\LegalDecisionDownloadFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class LegalDecisionDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-download.html';

    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.secret' => 'test-secret',
            'filesystems.disks.r2.bucket' => 'putusan-pdf',
            'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]);
        Storage::fake('r2');

        config(['services.ma_scraper.url' => 'https://scraper.test']);
        $this->pdf = '%PDF-1.4'.str_repeat('x', 600);
        Http::fake(['scraper.test/pdf*' => Http::response($this->pdf, 200, ['Content-Type' => 'application/pdf'])]);
    }

    private function download(User $user, bool $fromArchivePage = false): TestResponse
    {
        return $this->actingAs($user)->get(route('legal-decisions.pdf', array_filter(['url' => $this->url, 'title' => 'Putusan Test', 'download' => 1, 'archived' => $fromArchivePage ? 1 : null])));
    }

    private function objectPath(): string
    {
        return 'putusan_ma/'.md5($this->url).'.pdf';
    }

    public function test_first_download_uploads_pdf_to_r2_and_records_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();

        Storage::disk('r2')->assertExists($this->objectPath());
        $this->assertSame($this->pdf, Storage::disk('r2')->get($this->objectPath()));
        $this->assertSame('putusan-pdf/'.$this->objectPath(), DownloadedLegalDecision::sole()->metadata['r2_path']);
    }

    public function test_repeat_download_from_live_page_only_shows_info(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->download($admin)->assertOk();

        $this->actingAs($admin)
            ->from(route('legal-decisions.index'))
            ->get(route('legal-decisions.pdf', ['url' => $this->url, 'title' => 'Putusan Test', 'download' => 1]))
            ->assertRedirect(route('legal-decisions.index'))
            ->assertSessionHas('info', 'Putusan "Putusan Test" sudah ada di Putusan MA (download).');

        Http::assertSentCount(1);
        $this->assertSame(1, DownloadedLegalDecision::count());
    }

    public function test_existing_record_preview_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->download($admin)->assertOk();

        $this->actingAs($admin)->get(route('legal-decisions.pdf', ['url' => $this->url]))->assertOk();
    }

    public function test_existing_record_without_r2_path_is_uploaded_without_new_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'X', 'title' => 'X', 'metadata' => ['ip' => '127.0.0.1', 'supabase_path' => 'putusan-pdf/old.pdf']]);

        $this->download($admin, fromArchivePage: true)->assertOk();

        Storage::disk('r2')->assertExists($this->objectPath());
        $record = DownloadedLegalDecision::sole();
        $this->assertSame('127.0.0.1', $record->metadata['ip']);
        $this->assertSame('putusan-pdf/'.$this->objectPath(), $record->metadata['r2_path']);
    }

    public function test_archive_page_shows_who_downloaded_each_decision(): void
    {
        $downloader = User::factory()->create(['name' => 'Budi Pengunduh', 'role' => 'admin']);
        $this->download($downloader)->assertOk();
        DownloadedLegalDecision::create(['source_url' => $this->url.'?2', 'decision_number' => 'Putusan Tanpa Pengunduh', 'title' => 'Y', 'metadata' => ['r2_path' => 'putusan-pdf/y.pdf']]);

        $this->assertSame($downloader->id, DownloadedLegalDecision::query()->where('source_url', $this->url)->value('user_id'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('legal-decisions.downloads'))
            ->assertOk()
            ->assertSeeInOrder(['Putusan Tanpa Pengunduh', 'Diunduh oleh pengguna tidak diketahui', 'Putusan Test', 'Diunduh oleh Budi Pengunduh']);
    }

    public function test_preview_reads_from_r2_when_file_exists_even_without_r2_path(): void
    {
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Test', 'title' => 'X', 'metadata' => ['r2_path' => null]]);
        Storage::disk('r2')->put($this->objectPath(), '%PDF-1.7 dari bucket');

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('legal-decisions.pdf', ['url' => $this->url]))
            ->assertOk();

        $this->assertSame('%PDF-1.7 dari bucket', $response->streamedContent());
        Http::assertNothingSent();
    }

    public function test_archive_page_flags_decisions_whose_file_is_missing_in_r2(): void
    {
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Tanpa PDF', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/hilang.pdf']]);
        DownloadedLegalDecision::create(['source_url' => $this->url.'?2', 'decision_number' => 'Putusan Lengkap', 'title' => 'Y']);
        Storage::disk('r2')->put('putusan_ma/'.md5($this->url.'?2').'.pdf', '%PDF-1.4');

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('legal-decisions.downloads'))
            ->assertOk()
            ->assertSeeInOrder(['Putusan Lengkap', 'Diunduh oleh', 'Putusan Tanpa PDF', 'Diunduh oleh', 'File gagal terunduh']);

        $this->assertSame(1, substr_count($response->getContent(), 'File gagal terunduh'));
    }

    public function test_archive_page_can_be_filtered_by_file_status(): void
    {
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Tanpa PDF', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/hilang.pdf']]);
        DownloadedLegalDecision::create(['source_url' => $this->url.'?2', 'decision_number' => 'Putusan Lengkap', 'title' => 'Y']);
        Storage::disk('r2')->put('putusan_ma/'.md5($this->url.'?2').'.pdf', '%PDF-1.4');
        $admin = User::factory()->create(['role' => 'admin']);

        $missing = $this->actingAs($admin)->get(route('legal-decisions.downloads', ['file_status' => 'missing']))->assertOk();
        $this->assertSame(['Putusan Tanpa PDF'], collect($missing->viewData('decisions')->items())->pluck('decision_number')->all());

        $available = $this->actingAs($admin)->get(route('legal-decisions.downloads', ['file_status' => 'available']))->assertOk();
        $this->assertSame(['Putusan Lengkap'], collect($available->viewData('decisions')->items())->pluck('decision_number')->all());

        $all = $this->actingAs($admin)->get(route('legal-decisions.downloads', ['file_status' => 'invalid']))->assertOk();
        $this->assertSame(2, $all->viewData('decisions')->total());
    }

    public function test_live_page_download_repairs_archive_missing_pdf_in_r2(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Test', 'title' => 'X', 'metadata' => ['r2_path' => null]]);

        $this->download($admin)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        Storage::disk('r2')->assertExists($this->objectPath());
        $this->assertSame('putusan-pdf/'.$this->objectPath(), DownloadedLegalDecision::sole()->metadata['r2_path']);
    }

    public function test_download_skips_r2_when_not_configured(): void
    {
        config(['filesystems.disks.r2.endpoint' => null]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();

        Storage::disk('r2')->assertMissing($this->objectPath());
        $this->assertNull(DownloadedLegalDecision::sole()->metadata['r2_path']);
    }

    public function test_admin_can_view_and_search_downloaded_decisions_with_live_page_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Nomor 2866 PK/2026', 'title' => 'X', 'classification' => 'Pidana Khusus', 'year' => 2026]);
        DownloadedLegalDecision::create(['source_url' => $this->url.'?2', 'decision_number' => 'Putusan Nomor 1411 K/PDT/2026', 'title' => 'Y', 'classification' => 'Perdata', 'year' => 2026]);

        $this->actingAs($admin)->get(route('legal-decisions.downloads'))
            ->assertOk()
            ->assertSee('Putusan MA (Download)')
            ->assertSee('Pencarian Kata Kunci')
            ->assertSee('Putusan Nomor 2866 PK/2026')
            ->assertSee('Putusan Nomor 1411 K/PDT/2026')
            ->assertSee(route('legal-decisions.downloads', ['kategori' => 'Perdata', 'page' => 1]))
            ->assertSee('archived=1', false)
            ->assertDontSee('Sudah ada');

        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['search' => '2866']))
            ->assertOk()
            ->assertSee('Putusan Nomor 2866 PK/2026')
            ->assertDontSee('Putusan Nomor 1411 K/PDT/2026');

        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['kategori' => 'Perdata']))
            ->assertOk()
            ->assertSee('Putusan Nomor 1411 K/PDT/2026')
            ->assertDontSee('Putusan Nomor 2866 PK/2026');
    }

    public function test_downloaded_decisions_list_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('legal-decisions.downloads'))
            ->assertForbidden();
    }

    public function test_r2_failure_does_not_break_download_but_is_not_archived(): void
    {
        Storage::shouldReceive('disk->put')->andThrow(new RuntimeException('R2 down'));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();

        // Gagal tetap masuk arsip tanpa file, supaya tampil dengan peringatan "File gagal terunduh".
        $failed = DownloadedLegalDecision::sole();
        $this->assertNull($failed->metadata['r2_path']);
        $this->assertNotEmpty($failed->metadata['download_error']);
    }

    public function test_preview_does_not_upload_or_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('legal-decisions.pdf', ['url' => $this->url]))->assertOk();

        $this->assertSame([], Storage::disk('r2')->allFiles());
        $this->assertSame(0, DownloadedLegalDecision::count());
    }

    public function test_archived_decision_is_previewed_from_r2_without_local_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('r2')->put($this->objectPath(), '%PDF-1.4 from-r2'.str_repeat('y', 600));
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'X', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/'.$this->objectPath()]]);
        $response = $this->actingAs($admin)->get(route('legal-decisions.pdf', ['url' => $this->url, 'title' => 'Putusan Test']));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline; filename=Putusan_Test.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-1.4 from-r2', $response->streamedContent());
        Http::assertNothingSent();
    }

    public function test_archived_decision_is_downloaded_from_r2_as_attachment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('r2')->put($this->objectPath(), '%PDF-1.4 from-r2'.str_repeat('y', 600));
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'X', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/'.$this->objectPath()]]);

        $response = $this->download($admin, fromArchivePage: true);

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=Putusan_Test.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-1.4 from-r2', $response->streamedContent());
        $this->assertSame(1, DownloadedLegalDecision::count());
    }

    public function test_missing_r2_object_falls_back_to_scraper(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'X', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/'.$this->objectPath()]]);

        $this->actingAs($admin)->get(route('legal-decisions.pdf', ['url' => $this->url]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertContent($this->pdf);
    }

    public function test_preview_and_download_never_write_local_files(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $localPath = storage_path('app/putusan_ma/'.md5($this->url).'.pdf');

        $this->actingAs($admin)->get(route('legal-decisions.pdf', ['url' => $this->url]))->assertOk();
        $this->download($admin)->assertOk();

        $this->assertFileDoesNotExist($localPath);
    }

    public function test_sub_admin_with_permission_can_access_legal_decisions(): void
    {
        $subAdmin = User::factory()->create(['role' => 'sub_admin', 'permissions' => ['access_legal_decisions']]);

        $this->actingAs($subAdmin)->get(route('legal-decisions.downloads'))
            ->assertOk()
            ->assertSee(route('legal-decisions.index'));
        $this->actingAs($subAdmin)->get(route('legal-decisions.index'))->assertOk();
        $this->actingAs($subAdmin)->get(route('legal-decisions.pdf', ['url' => $this->url]))->assertOk();
    }

    public function test_sub_admin_without_permission_cannot_access_legal_decisions(): void
    {
        $subAdmin = User::factory()->create(['role' => 'sub_admin', 'permissions' => ['manage_categories']]);

        $this->actingAs($subAdmin)->get(route('legal-decisions.index'))->assertForbidden();
        $this->actingAs($subAdmin)->get(route('legal-decisions.downloads'))->assertForbidden();
        $this->actingAs($subAdmin)->get(route('legal-decisions.pdf', ['url' => $this->url]))->assertForbidden();
        $this->actingAs($subAdmin)->get(route('dashboard'))->assertDontSee(route('legal-decisions.index'));
    }

    public function test_reviewer_cannot_access_legal_decisions_even_with_permission_value(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer', 'permissions' => ['access_legal_decisions']]);

        $this->actingAs($reviewer)->get(route('legal-decisions.index'))->assertForbidden();
    }

    public function test_archive_page_marks_r2_documents_as_cloud_preview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Arsip', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/'.$this->objectPath()]]);

        $html = $this->actingAs($admin)->get(route('legal-decisions.downloads'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, "pdfSource: 'cloud'"));
        $this->assertSame(1, substr_count($html, "pdfSource: 'ma'"), 'Hanya default activeDoc di JS yang boleh bernilai ma');
    }

    public function test_downloads_page_displays_user_recap_and_supports_user_filter(): void
    {
        $admin = User::factory()->create(['name' => 'Budi Auditor', 'role' => 'admin']);
        $userA = User::factory()->create(['name' => 'Tegar Dev', 'email' => 'tegar@example.com']);
        $userB = User::factory()->create(['name' => 'Siti Staff', 'email' => 'siti@example.com']);

        // User A: 2 berhasil, 1 gagal
        DownloadedLegalDecision::create([
            'user_id' => $userA->id,
            'source_url' => 'https://example.com/putusan-a1.html',
            'decision_number' => 'Putusan A1',
            'title' => 'Perkara A1',
        ]);
        DownloadedLegalDecision::create([
            'user_id' => $userA->id,
            'source_url' => 'https://example.com/putusan-a2.html',
            'decision_number' => 'Putusan A2',
            'title' => 'Perkara A2',
        ]);
        LegalDecisionDownloadFailure::recordFailure(
            url: 'https://example.com/putusan-a3.html',
            errorMessage: 'Timeout',
            decisionNumber: 'Putusan A3 Gagal',
            userId: $userA->id,
        );

        // User B: 1 berhasil, 0 gagal
        DownloadedLegalDecision::create([
            'user_id' => $userB->id,
            'source_url' => 'https://example.com/putusan-b1.html',
            'decision_number' => 'Putusan B1',
            'title' => 'Perkara B1',
        ]);

        $response = $this->actingAs($admin)->get(route('legal-decisions.downloads'));
        $response->assertOk();
        $response->assertSee('Rekap Hasil Download per Pengguna');
        $response->assertSee('Tegar Dev');
        $response->assertSee('Siti Staff');

        // Filter berdasarkan User A
        $filterResponse = $this->actingAs($admin)->get(route('legal-decisions.downloads', ['user_id' => $userA->id]));
        $filterResponse->assertOk();
        $filterResponse->assertSee('Putusan A1');
        $filterResponse->assertSee('Putusan A2');
        $filterResponse->assertDontSee('Putusan B1');
    }

    public function test_download_recap_matches_file_status_filter_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $downloader = User::factory()->create(['name' => 'Salahudin']);

        DownloadedLegalDecision::create(['user_id' => $downloader->id, 'source_url' => $this->url.'?ok', 'decision_number' => 'Putusan Lengkap', 'title' => 'A', 'file_size' => 100]);
        Storage::disk('r2')->put('putusan_ma/'.md5($this->url.'?ok').'.pdf', '%PDF-1.4');
        DownloadedLegalDecision::create(['user_id' => $downloader->id, 'source_url' => $this->url.'?hilang', 'decision_number' => 'Putusan Hilang', 'title' => 'B', 'file_size' => 100]);
        LegalDecisionDownloadFailure::recordFailure(url: $this->url.'?tidak-diarsip', errorMessage: 'Timeout', decisionNumber: 'Tidak Diarsip', userId: $downloader->id);

        $response = $this->actingAs($admin)->get(route('legal-decisions.downloads'))->assertOk();
        $stat = $response->viewData('userDownloadStats')->firstWhere('user_id', $downloader->id);

        $available = $this->actingAs($admin)->get(route('legal-decisions.downloads', ['file_status' => 'available']))->viewData('decisions')->total();
        $missing = $this->actingAs($admin)->get(route('legal-decisions.downloads', ['file_status' => 'missing']))->viewData('decisions')->total();

        $this->assertSame([1, 1, 2], [$stat->success_count, $stat->failure_count, $stat->total_count]);
        $this->assertSame([$available, $missing], [$stat->success_count, $stat->failure_count]);
    }

    public function test_backfill_command_moves_unarchived_failures_into_archive_with_warning(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Salahudin']);
        LegalDecisionDownloadFailure::recordFailure(url: $this->url, errorMessage: 'Timeout', decisionNumber: 'Putusan Gagal Lama', userId: $admin->id);
        LegalDecisionDownloadFailure::recordFailure(url: $this->url.'?arsip', errorMessage: 'Timeout', decisionNumber: 'Sudah Diarsip', userId: $admin->id);
        DownloadedLegalDecision::create(['source_url' => $this->url.'?arsip', 'decision_number' => 'Sudah Diarsip', 'title' => 'X']);

        $this->artisan('legal-decisions:backfill-failures')->assertSuccessful();
        $this->artisan('legal-decisions:backfill-failures')->assertSuccessful();

        $this->assertSame(2, DownloadedLegalDecision::count());
        $record = DownloadedLegalDecision::query()->where('source_url', $this->url)->sole();
        $this->assertSame($admin->id, $record->user_id);
        $this->assertSame('Timeout', $record->metadata['download_error']);

        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['file_status' => 'missing']))
            ->assertOk()
            ->assertSeeInOrder(['Putusan Gagal Lama', 'Diunduh oleh Salahudin', 'File gagal terunduh', 'Unduh Ulang']);
    }
}
