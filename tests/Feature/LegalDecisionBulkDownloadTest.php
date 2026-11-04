<?php

namespace Tests\Feature;

use App\Jobs\DownloadLegalDecisionJob;
use App\Models\DownloadedLegalDecision;
use App\Models\User;
use App\Services\LegalDecisionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
