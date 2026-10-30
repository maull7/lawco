<?php

namespace Tests\Feature;

use App\Jobs\DownloadLegalDecisionJob;
use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        $job->handle();

        $this->assertDatabaseHas('downloaded_legal_decisions', [
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/sample123.html',
            'decision_number' => 'Putusan MA Nomor 999 K/Pdt/2026',
            'classification' => 'Perdata > Wanprestasi',
            'year' => '2026',
            'download_type' => 'bulk',
        ]);
    }
}
