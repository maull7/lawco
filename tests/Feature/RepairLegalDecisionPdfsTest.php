<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class RepairLegalDecisionPdfsTest extends TestCase
{
    use RefreshDatabase;

    private string $okUrl = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/ok.html';

    private string $missingUrl = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/missing.html';

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
    }

    public function test_repairs_records_missing_pdf_and_reports_failures(): void
    {
        Http::fake([
            'scraper.test/pdf?url='.urlencode($this->okUrl) => Http::response('%PDF-1.4'.str_repeat('x', 600), 200),
            'scraper.test/pdf*' => Http::response(['success' => false], 404),
        ]);
        $ok = DownloadedLegalDecision::create(['source_url' => $this->okUrl, 'decision_number' => 'OK', 'title' => 'OK', 'metadata' => ['r2_path' => null]]);
        $missing = DownloadedLegalDecision::create(['source_url' => $this->missingUrl, 'decision_number' => 'Missing', 'title' => 'Missing']);
        $complete = DownloadedLegalDecision::create(['source_url' => $this->okUrl.'?done', 'decision_number' => 'Done', 'title' => 'Done', 'metadata' => ['r2_path' => 'putusan-pdf/done.pdf']]);

        $this->artisan('legal-decisions:repair-pdfs')
            ->expectsOutputToContain('1 berhasil dilengkapi, 1 gagal.')
            ->assertFailed();

        Storage::disk('r2')->assertExists('putusan_ma/'.md5($this->okUrl).'.pdf');
        $this->assertSame('putusan-pdf/putusan_ma/'.md5($this->okUrl).'.pdf', $ok->fresh()->metadata['r2_path']);
        $this->assertNull($missing->fresh()->metadata);
        $this->assertSame('putusan-pdf/done.pdf', $complete->fresh()->metadata['r2_path']);
        Http::assertSentCount(2);
    }

    public function test_dry_run_changes_nothing(): void
    {
        Http::fake();
        DownloadedLegalDecision::create(['source_url' => $this->okUrl, 'decision_number' => 'OK', 'title' => 'OK']);

        $this->artisan('legal-decisions:repair-pdfs', ['--dry-run' => true])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_fails_when_r2_is_not_configured(): void
    {
        config(['filesystems.disks.r2.endpoint' => null]);

        $this->artisan('legal-decisions:repair-pdfs')
            ->expectsOutputToContain('Cloudflare R2 belum dikonfigurasi')
            ->assertFailed();
    }

    public function test_reports_when_all_records_are_complete(): void
    {
        DownloadedLegalDecision::create(['source_url' => $this->okUrl, 'decision_number' => 'OK', 'title' => 'OK', 'metadata' => ['r2_path' => 'putusan-pdf/x.pdf']]);

        $this->artisan('legal-decisions:repair-pdfs')
            ->expectsOutputToContain('Semua putusan di arsip sudah memiliki PDF di R2.')
            ->assertSuccessful();
    }
}
