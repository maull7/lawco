<?php

namespace Tests\Feature;

use App\Jobs\DownloadLegalDecisionJob;
use App\Models\DownloadedLegalDecision;
use App\Models\User;
use App\Services\LegalDecisionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalDecisionActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-activity.html';

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'permissions' => ['access_legal_decisions' => true],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ma_scraper.url' => 'https://scraper.test',
            'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.secret' => 'test-secret',
            'filesystems.disks.r2.bucket' => 'putusan-pdf',
            'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]);
        Storage::fake('r2');
    }

    public function test_single_download_creates_activity_log_visible_in_user_show(): void
    {
        Http::fake([
            'scraper.test/pdf*' => Http::response('%PDF-1.4 sample content'.str_repeat('x', 600), 200, ['Content-Type' => 'application/pdf']),
        ]);

        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('legal-decisions.download-single'), [
            'url' => $this->url,
            'decision_number' => 'Putusan Activity Log 123',
        ]);

        $response->assertOk();

        // Cek log di database
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $admin->id,
            'action' => 'downloaded',
            'subject_type' => DownloadedLegalDecision::class,
        ]);

        // Cek halaman Activity Log user
        $viewResponse = $this->actingAs($admin)->get(route('users.show', $admin->id));
        $viewResponse->assertOk()
            ->assertSee('Mengunduh')
            ->assertSee('Putusan MA')
            ->assertSee('Putusan Activity Log 123');
    }

    public function test_bulk_download_job_creates_activity_log_for_user(): void
    {
        Http::fake([
            'scraper.test/pdf*' => Http::response('%PDF-1.4 sample content'.str_repeat('x', 600), 200, ['Content-Type' => 'application/pdf']),
        ]);

        $admin = $this->admin();
        $job = new DownloadLegalDecisionJob([
            'url' => $this->url.'?bulk',
            'decision_number' => 'Putusan Bulk 999',
        ], $admin->id);

        $job->handle(app(LegalDecisionPdfService::class));

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $admin->id,
            'action' => 'downloaded',
            'subject_type' => DownloadedLegalDecision::class,
        ]);
    }

    public function test_deleting_downloaded_decision_creates_activity_log(): void
    {
        $admin = $this->admin();
        $decision = DownloadedLegalDecision::create([
            'user_id' => $admin->id,
            'source_url' => $this->url,
            'decision_number' => 'Putusan Hapus Test',
            'title' => 'Putusan Hapus Test',
        ]);

        $this->actingAs($admin)->delete(route('legal-decisions.downloads.destroy', $decision->id));

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $admin->id,
            'action' => 'deleted',
            'subject_type' => DownloadedLegalDecision::class,
        ]);
    }

    public function test_failed_download_creates_download_failed_activity_log(): void
    {
        Http::fake([
            'scraper.test/pdf*' => Http::response(null, 404),
        ]);

        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('legal-decisions.download-single'), [
            'url' => $this->url,
            'decision_number' => 'Putusan Gagal Test 555',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $admin->id,
            'action' => 'download_failed',
        ]);

        $viewResponse = $this->actingAs($admin)->get(route('users.show', $admin->id));
        $viewResponse->assertOk()
            ->assertSee('Gagal Unduh')
            ->assertSee('Putusan MA');
    }
}
