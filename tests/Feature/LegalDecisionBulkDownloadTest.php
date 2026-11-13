<?php

namespace Tests\Feature;

use App\Jobs\DownloadLegalDecisionJob;
use App\Models\DownloadedLegalDecision;
use App\Models\User;
use App\Services\LegalDecisionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class LegalDecisionBulkDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['access_legal_decisions'],
        ]);
    }

    public function test_guest_cannot_trigger_bulk_download(): void
    {
        $response = $this->postJson(route('legal-decisions.bulk-download'), [
            'decisions' => [
                ['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/123.html'],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_validation_requires_decisions_array(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['decisions']);
    }

    public function test_bulk_download_dispatches_jobs_to_queue(): void
    {
        Queue::fake();

        $payload = [
            'decisions' => [
                [
                    'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/item1.html',
                    'decision_number' => 'Putusan MA Nomor 100/Pdt/2026',
                    'court_name' => 'Mahkamah Agung',
                    'classification' => 'Perdata',
                    'year' => 2026,
                    'summary' => 'Ringkasan perkara 1',
                    'tgl_register' => '01-01-2026',
                    'tgl_putus' => '10-02-2026',
                    'tgl_upload' => '20-02-2026',
                ],
                [
                    'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/item2.html',
                    'decision_number' => 'Putusan MA Nomor 200/Pid/2026',
                    'court_name' => 'Mahkamah Agung',
                    'classification' => 'Pidana',
                    'year' => 2026,
                    'summary' => 'Ringkasan perkara 2',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'queued_count' => 2,
            'skipped_count' => 0,
        ]);

        Queue::assertPushed(DownloadLegalDecisionJob::class, 2);
        Queue::assertPushedOn('ma-download', DownloadLegalDecisionJob::class);
    }

    public function test_bulk_download_skips_already_downloaded_items(): void
    {
        Queue::fake();

        DownloadedLegalDecision::create([
            'user_id' => $this->admin->id,
            'decision_number' => 'Putusan MA Nomor 100/Pdt/2026',
            'title' => 'Putusan MA Nomor 100/Pdt/2026',
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/item1.html',
            'metadata' => ['r2_path' => 'putusan-pdf/putusan_ma/item1.pdf'],
        ]);

        $payload = [
            'decisions' => [
                [
                    'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/item1.html',
                    'decision_number' => 'Putusan MA Nomor 100/Pdt/2026',
                ],
                [
                    'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/item2.html',
                    'decision_number' => 'Putusan MA Nomor 200/Pid/2026',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'queued_count' => 1,
            'skipped_count' => 1,
        ]);

        Queue::assertPushed(DownloadLegalDecisionJob::class, 1);
    }

    public function test_download_job_creates_downloaded_record_successfully(): void
    {
        config(['services.ma_scraper.url' => 'http://127.0.0.1:8001']);
        config(['services.ma_scraper.token' => 'test-token']);

        $fakePdfContent = "%PDF-1.4 Fake legal decision document content for testing\n".str_repeat('x', 600);
        Http::fake([
            'http://127.0.0.1:8001/pdf*' => Http::response($fakePdfContent, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $decisionData = [
            'url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/sample123.html',
            'decision_number' => 'Putusan MA Nomor 999 K/Pdt/2026',
            'court_name' => 'Mahkamah Agung',
            'classification' => 'Perdata > Wanprestasi',
            'year' => 2026,
            'summary' => 'Gugatan wanprestasi',
            'tgl_register' => '05-01-2026',
            'tgl_putus' => '15-03-2026',
            'tgl_upload' => '25-03-2026',
        ];

        $job = new DownloadLegalDecisionJob($decisionData, $this->admin->id);
        $job->handle(app(LegalDecisionPdfService::class));

        $this->assertDatabaseHas('downloaded_legal_decisions', [
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/sample123.html',
            'decision_number' => 'Putusan MA Nomor 999 K/Pdt/2026',
            'classification' => 'Perdata > Wanprestasi',
            'year' => '2026',
            'download_type' => 'bulk',
        ]);
    }

    private function configureScraperAndR2(): void
    {
        Sleep::fake();
        config([
            'services.ma_scraper.url' => 'https://scraper.test',
            'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.secret' => 'test-secret',
            'filesystems.disks.r2.bucket' => 'putusan-pdf',
            'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]);
        Storage::fake('r2');
    }

    public function test_download_job_fails_with_reason_and_saves_nothing_when_pdf_unavailable(): void
    {
        $this->configureScraperAndR2();
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false, 'error' => 'Link PDF tidak ditemukan'], 404)]);

        $job = (new DownloadLegalDecisionJob(['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/kosong.html'], $this->admin->id))
            ->withFakeQueueInteractions();
        $job->handle(app(LegalDecisionPdfService::class));

        $job->assertFailedWith(fn (\Throwable $e): bool => str_contains($e->getMessage(), 'File PDF putusan tidak ditemukan di portal Mahkamah Agung'));
        // Gagal tetap masuk arsip tanpa file, supaya tampil dengan peringatan "File gagal terunduh".
        $failed = DownloadedLegalDecision::sole();
        $this->assertNull($failed->metadata['r2_path']);
        $this->assertNotEmpty($failed->metadata['download_error']);
    }

    public function test_download_job_is_released_without_failure_when_portal_times_out(): void
    {
        $this->configureScraperAndR2();
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false, 'timeout' => true, 'error' => 'curl: (28) Operation timed out'], 504)]);

        $job = (new DownloadLegalDecisionJob(['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/lambat.html'], $this->admin->id))
            ->withFakeQueueInteractions();
        $job->handle(app(LegalDecisionPdfService::class));

        $job->assertReleased(LegalDecisionPdfService::TIMEOUT_RETRY_SECONDS);
        $this->assertSame(0, DownloadedLegalDecision::count());
    }

    public function test_download_job_repairs_existing_record_without_pdf_in_r2(): void
    {
        $this->configureScraperAndR2();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/lama.html';
        Http::fake(['scraper.test/pdf*' => Http::response('%PDF-1.4'.str_repeat('x', 600), 200)]);
        DownloadedLegalDecision::create(['source_url' => $url, 'decision_number' => 'Lama', 'title' => 'Lama', 'metadata' => ['r2_path' => null]]);

        (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->handle(app(LegalDecisionPdfService::class));

        Storage::disk('r2')->assertExists('putusan_ma/'.md5($url).'.pdf');
        $this->assertSame('putusan-pdf/putusan_ma/'.md5($url).'.pdf', DownloadedLegalDecision::sole()->metadata['r2_path']);
    }

    public function test_download_status_reports_queued_done_failed_and_unknown(): void
    {
        $base = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/';
        foreach (['antri' => ['r2_path' => null, 'queued_at' => now()->toISOString()], 'selesai' => ['r2_path' => 'putusan-pdf/putusan_ma/x.pdf'], 'gagal' => ['r2_path' => null, 'download_error' => 'Portal MA error']] as $slug => $metadata) {
            DownloadedLegalDecision::create(['source_url' => $base.$slug.'.html', 'decision_number' => $slug, 'title' => $slug, 'metadata' => $metadata]);
        }

        $this->actingAs($this->admin)
            ->postJson(route('legal-decisions.download-status'), ['urls' => [$base.'antri.html', $base.'selesai.html', $base.'gagal.html', $base.'baru.html']])
            ->assertOk()
            ->assertJson(['statuses' => [
                $base.'antri.html' => ['status' => 'queued', 'error' => null],
                $base.'selesai.html' => ['status' => 'done', 'error' => null],
                $base.'gagal.html' => ['status' => 'failed', 'error' => 'Portal MA error'],
                $base.'baru.html' => ['status' => 'unknown', 'error' => null],
            ]]);
    }

    public function test_bulk_download_requeues_existing_record_missing_pdf_in_r2(): void
    {
        Queue::fake();
        $this->configureScraperAndR2();
        DownloadedLegalDecision::create([
            'decision_number' => 'Lama',
            'title' => 'Lama',
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/lama.html',
            'metadata' => ['r2_path' => null],
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('legal-decisions.bulk-download'), ['decisions' => [['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/lama.html']]])
            ->assertOk()
            ->assertJson(['queued_count' => 1, 'skipped_count' => 0]);

        Queue::assertPushed(DownloadLegalDecisionJob::class, 1);
    }

    public function test_bulk_download_records_queued_decision_for_user_immediately(): void
    {
        Queue::fake();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/antri.html';

        $this->actingAs($this->admin)
            ->postJson(route('legal-decisions.bulk-download'), ['decisions' => [['url' => $url, 'decision_number' => 'Putusan Antri']]])
            ->assertOk();

        $record = DownloadedLegalDecision::sole();
        $this->assertSame($this->admin->id, $record->user_id);
        $this->assertTrue($record->isQueued());
        $this->assertFalse($record->isPdfMissing());
        // Masih antri: belum dihitung gagal di rekap per pengguna.
        $this->assertSame(0, DownloadedLegalDecision::query()->pdfMissing()->count());
    }

    public function test_bulk_download_does_not_dispatch_again_while_decision_is_queued(): void
    {
        Queue::fake();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/antri.html';
        $payload = ['decisions' => [['url' => $url]]];

        $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), $payload)->assertOk();

        Queue::assertPushed(DownloadLegalDecisionJob::class, 1);
        $this->assertSame(1, DownloadedLegalDecision::count());
    }

    public function test_download_job_completes_queued_record_without_new_row(): void
    {
        $this->configureScraperAndR2();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/antri.html';
        Http::fake(['scraper.test/pdf*' => Http::response('%PDF-1.4'.str_repeat('x', 600), 200)]);
        app(LegalDecisionPdfService::class)->recordQueuedDownload($url, ['decision_number' => 'Putusan Antri'], $this->admin->id);

        (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->handle(app(LegalDecisionPdfService::class));

        $record = DownloadedLegalDecision::sole();
        $this->assertFalse($record->isQueued());
        $this->assertNotEmpty($record->metadata['r2_path']);
        $this->assertSame(1, DownloadedLegalDecision::query()->pdfAvailable()->where('user_id', $this->admin->id)->count());
    }

    public function test_download_job_marks_queued_record_failed_when_pdf_unavailable(): void
    {
        $this->configureScraperAndR2();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/kosong.html';
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false, 'error' => 'Link PDF tidak ditemukan'], 404)]);
        app(LegalDecisionPdfService::class)->recordQueuedDownload($url, [], $this->admin->id);

        (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->withFakeQueueInteractions()->handle(app(LegalDecisionPdfService::class));

        $record = DownloadedLegalDecision::sole();
        $this->assertFalse($record->isQueued());
        $this->assertNotEmpty($record->metadata['download_error']);
        $this->assertSame(1, DownloadedLegalDecision::query()->pdfMissing()->count());
    }

    public function test_failed_job_marks_still_queued_record_as_failed(): void
    {
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/antri.html';
        app(LegalDecisionPdfService::class)->recordQueuedDownload($url, [], $this->admin->id);

        (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->failed(new \RuntimeException('timeout'));

        $record = DownloadedLegalDecision::sole();
        $this->assertFalse($record->isQueued());
        $this->assertStringContainsString('timeout', $record->metadata['download_error']);
    }

    public function test_queued_decision_is_listed_on_downloads_page(): void
    {
        Queue::fake();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/antri.html';

        $this->actingAs($this->admin)
            ->postJson(route('legal-decisions.bulk-download'), ['decisions' => [['url' => $url, 'decision_number' => 'Putusan Antri 77']]])
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('legal-decisions.downloads'))
            ->assertOk()
            ->assertSee('Putusan Antri 77')
            ->assertSee('Dalam antrian')
            ->assertSee('Antrian Download')
            ->assertSee('Dalam Antrian');
    }

    public function test_recap_counts_queued_decisions_per_user_with_eta(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), ['decisions' => [
            ['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/a1.html', 'decision_number' => 'A1'],
            ['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/a2.html', 'decision_number' => 'A2'],
        ]])->assertOk();

        $response = $this->actingAs($this->admin)->get(route('legal-decisions.downloads'))->assertOk();

        $stat = $response->viewData('userDownloadStats')->firstWhere('user_id', $this->admin->id);
        $this->assertSame(2, $stat->queued_count);
        $this->assertSame(0, $stat->failure_count);

        $items = $response->viewData('queue')['items'];
        $this->assertSame([1, 2], $items->pluck('position')->all());
        $this->assertTrue(now()->lessThan($items[0]->eta_at) && $items[0]->eta_at < $items[1]->eta_at);
    }

    public function test_queue_eta_does_not_grow_on_refresh_and_flags_stalled_queue(): void
    {
        Queue::fake();
        $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), ['decisions' => [
            ['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/a1.html', 'decision_number' => 'A1'],
        ]])->assertOk();

        $firstEta = $this->actingAs($this->admin)->get(route('legal-decisions.downloads'))->viewData('queue')['items'][0]->eta_at;

        $this->travel(10)->minutes();
        $response = $this->actingAs($this->admin)->get(route('legal-decisions.downloads'));

        $this->assertSame($firstEta, $response->viewData('queue')['items'][0]->eta_at);
        $this->assertTrue($response->viewData('queue')['stalled']);
    }

    public function test_queue_seconds_per_download_uses_recent_real_durations(): void
    {
        $service = app(LegalDecisionPdfService::class);
        $this->assertSame(LegalDecisionPdfService::QUEUE_SECONDS_PER_DOWNLOAD, $service->queueSecondsPerDownload());

        Cache::flush();
        // Selesai tiap 12 detik, lalu jeda 20 menit (antrian kosong) yang harus diabaikan.
        foreach ([0, 12, 24, 36, 1236] as $index => $offset) {
            $record = DownloadedLegalDecision::create([
                'decision_number' => 'D'.$index,
                'title' => 'D'.$index,
                'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/d'.$index.'.html',
                'download_type' => 'bulk',
                'metadata' => ['r2_path' => 'putusan-pdf/d'.$index.'.pdf'],
            ]);
            $record->timestamps = false;
            $record->forceFill(['updated_at' => now()->subHour()->addSeconds($offset)])->save();
        }

        $this->assertSame(12, $service->queueSecondsPerDownload());
    }

    public function test_queue_status_shows_only_own_queue_to_non_admin_and_everything_to_admin(): void
    {
        Queue::fake();
        $staff = User::factory()->create(['role' => 'sub_admin', 'permissions' => ['access_legal_decisions']]);

        $this->actingAs($this->admin)->postJson(route('legal-decisions.bulk-download'), ['decisions' => [
            ['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/admin1.html', 'decision_number' => 'Admin 1'],
        ]])->assertOk();
        $this->actingAs($staff)->postJson(route('legal-decisions.bulk-download'), ['decisions' => [
            ['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/staff1.html', 'decision_number' => 'Staff 1'],
        ]])->assertOk();

        $staffQueue = $this->actingAs($staff)->getJson(route('legal-decisions.queue-status'))->assertOk();
        $staffQueue->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.decision_number', 'Staff 1')
            ->assertJsonPath('items.0.position', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('is_admin', false)
            ->assertJsonCount(0, 'users');

        $adminQueue = $this->actingAs($this->admin)->getJson(route('legal-decisions.queue-status'))->assertOk();
        $adminQueue->assertJsonCount(2, 'items')
            ->assertJsonPath('is_admin', true)
            ->assertJsonCount(2, 'users')
            ->assertJsonPath('users.1.user_name', $staff->name)
            ->assertJsonPath('users.1.count', 1);
    }

    public function test_queued_download_waits_while_scraper_is_offline_and_resumes_when_back(): void
    {
        $this->configureScraperAndR2();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/offline.html';
        $service = app(LegalDecisionPdfService::class);
        $service->recordQueuedDownload($url, ['decision_number' => 'Putusan Offline'], $this->admin->id);

        // Laptop scraper mati: koneksi gagal. Job ditunda 1 menit, record tetap "Dalam antrian".
        $online = false;
        Http::fake(['scraper.test/pdf*' => function () use (&$online) {
            return $online ? Http::response('%PDF-1.4'.str_repeat('x', 600), 200) : Http::failedConnection();
        }]);
        $job = (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->withFakeQueueInteractions();
        $job->handle($service);

        $job->assertReleased(LegalDecisionPdfService::OFFLINE_RETRY_SECONDS);
        $job->assertNotFailed();
        $this->assertTrue(DownloadedLegalDecision::sole()->isQueued());
        $this->assertNotNull($service->scraperOfflineSince());
        $this->actingAs($this->admin)->getJson(route('legal-decisions.queue-status'))
            ->assertJsonPath('scraper_offline', true)
            ->assertJsonPath('stalled', false);

        // Scraper aktif lagi: percobaan berikutnya langsung berhasil dan status offline hilang.
        $online = true;
        (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->handle($service);

        $record = DownloadedLegalDecision::sole();
        $this->assertFalse($record->isQueued());
        $this->assertNotEmpty($record->metadata['r2_path']);
        $this->assertNull($service->scraperOfflineSince());
    }

    public function test_ngrok_error_page_is_treated_as_scraper_offline_not_missing_pdf(): void
    {
        $this->configureScraperAndR2();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/ngrok.html';
        // ngrok membalas 404 sendiri saat endpoint offline; jangan dibaca sebagai "PDF tidak ditemukan".
        Http::fake(['scraper.test/pdf*' => Http::response('<html>ERR_NGROK_3200</html>', 404, ['Ngrok-Error-Code' => 'ERR_NGROK_3200'])]);

        $job = (new DownloadLegalDecisionJob(['url' => $url], $this->admin->id))->withFakeQueueInteractions();
        $job->handle(app(LegalDecisionPdfService::class));

        $job->assertReleased(LegalDecisionPdfService::OFFLINE_RETRY_SECONDS);
        $job->assertNotFailed();
        $this->assertSame(0, DownloadedLegalDecision::count());
    }

    public function test_download_job_keeps_retrying_through_the_weekend(): void
    {
        $job = new DownloadLegalDecisionJob(['url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/x.html']);

        $this->assertTrue(now()->addHours(71)->lessThan($job->retryUntil()));
    }
}
