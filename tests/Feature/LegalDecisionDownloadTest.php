<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
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

    private function download(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('legal-decisions.pdf', ['url' => $this->url, 'title' => 'Putusan Test', 'download' => 1]));
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

    public function test_repeat_download_is_served_from_r2_without_new_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();
        $this->download(User::factory()->create(['role' => 'admin']))->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(1, DownloadedLegalDecision::count());
    }

    public function test_existing_record_without_r2_path_is_uploaded_without_new_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'X', 'title' => 'X', 'metadata' => ['ip' => '127.0.0.1', 'supabase_path' => 'putusan-pdf/old.pdf']]);

        $this->download($admin)->assertOk();

        Storage::disk('r2')->assertExists($this->objectPath());
        $record = DownloadedLegalDecision::sole();
        $this->assertSame('127.0.0.1', $record->metadata['ip']);
        $this->assertSame('putusan-pdf/'.$this->objectPath(), $record->metadata['r2_path']);
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
            ->assertSee(route('legal-decisions.downloads', ['kategori' => 'Perdata', 'page' => 1]));

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

    public function test_r2_failure_does_not_break_download(): void
    {
        Storage::shouldReceive('disk->put')->andThrow(new RuntimeException('R2 down'));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();

        $this->assertNull(DownloadedLegalDecision::sole()->metadata['r2_path']);
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

        $response = $this->download($admin);

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
}
