<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LegalDecisionDownloadSingleTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-single.html';

    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config([
            'services.ma_scraper.url' => 'https://scraper.test',
            'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.secret' => 'test-secret',
            'filesystems.disks.r2.bucket' => 'putusan-pdf',
            'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]);
        Storage::fake('r2');
        $this->pdf = '%PDF-1.4'.str_repeat('x', 600);
    }

    private function downloadSingle(): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('legal-decisions.download-single'), ['url' => $this->url, 'decision_number' => 'Putusan Single']);
    }

    private function objectPath(): string
    {
        return 'putusan_ma/'.md5($this->url).'.pdf';
    }

    public function test_successful_download_saves_record_and_pdf_to_r2(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response($this->pdf, 200, ['Content-Type' => 'application/pdf'])]);

        $this->downloadSingle()->assertOk()->assertJson(['success' => true, 'already_exists' => false]);

        Storage::disk('r2')->assertExists($this->objectPath());
        $record = DownloadedLegalDecision::sole();
        $this->assertSame('putusan-pdf/'.$this->objectPath(), $record->metadata['r2_path']);
        $this->assertSame(strlen($this->pdf), $record->file_size);
    }

    public function test_unreachable_scraper_returns_reason_and_saves_nothing(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::failedConnection()]);

        $this->downloadSingle()
            ->assertUnprocessable()
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Koneksi ke layanan scraper terputus'));

        $this->assertSame(0, DownloadedLegalDecision::count());
        Http::assertSentCount(3);
    }

    public function test_missing_pdf_on_ma_portal_returns_reason_and_saves_nothing(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false, 'error' => 'Link PDF tidak ditemukan'], 404)]);

        $this->downloadSingle()
            ->assertUnprocessable()
            ->assertJsonPath('message', 'File PDF putusan tidak ditemukan di portal Mahkamah Agung. (Link PDF tidak ditemukan)');

        $this->assertSame(0, DownloadedLegalDecision::count());
    }

    public function test_non_pdf_response_is_rejected(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response('<html>'.str_repeat('x', 600).'</html>', 200)]);

        $this->downloadSingle()
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'tidak mengembalikan file PDF yang valid'));

        $this->assertSame(0, DownloadedLegalDecision::count());
    }

    public function test_invalid_scraper_api_key_returns_reason(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response(['success' => false, 'error' => 'Unauthorized'], 401)]);

        $this->downloadSingle()
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Layanan scraper menolak akses: API key (MA_SCRAPER_TOKEN) tidak valid.');
    }

    public function test_r2_upload_failure_returns_reason_and_saves_nothing(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response($this->pdf, 200)]);
        Storage::shouldReceive('disk->put')->andThrow(new \RuntimeException('R2 down'));

        $this->downloadSingle()
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'gagal disimpan ke Cloudflare R2'));

        $this->assertSame(0, DownloadedLegalDecision::count());
    }

    public function test_existing_record_in_r2_is_reported_as_already_exists(): void
    {
        Http::fake();
        Storage::disk('r2')->put($this->objectPath(), $this->pdf);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Single', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/'.$this->objectPath()]]);

        $this->downloadSingle()->assertOk()->assertJson(['success' => true, 'already_exists' => true]);

        Http::assertNothingSent();
    }

    public function test_existing_record_whose_r2_file_is_missing_is_downloaded_again(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response($this->pdf, 200)]);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Single', 'title' => 'X', 'metadata' => ['r2_path' => 'putusan-pdf/'.$this->objectPath()]]);

        $this->downloadSingle()->assertOk()->assertJson(['success' => true, 'already_exists' => false]);

        Storage::disk('r2')->assertExists($this->objectPath());
        $this->assertSame(1, DownloadedLegalDecision::count());
    }

    public function test_existing_record_without_pdf_is_repaired_without_new_row(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::response($this->pdf, 200)]);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Single', 'title' => 'X', 'metadata' => ['r2_path' => null]]);

        $this->downloadSingle()->assertOk()->assertJson(['success' => true, 'already_exists' => false]);

        Storage::disk('r2')->assertExists($this->objectPath());
        $this->assertSame('putusan-pdf/'.$this->objectPath(), DownloadedLegalDecision::sole()->metadata['r2_path']);
    }

    public function test_existing_record_without_pdf_reports_reason_when_repair_fails(): void
    {
        Http::fake(['scraper.test/pdf*' => Http::failedConnection()]);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'Putusan Single', 'title' => 'X', 'metadata' => ['r2_path' => null]]);

        $this->downloadSingle()
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Putusan sudah tercatat, tetapi PDF-nya belum tersimpan'));

        $this->assertSame(1, DownloadedLegalDecision::count());
    }
}
