<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LegalDecisionDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-download.html';

    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.key' => 'test-key',
            'services.supabase.bucket' => 'putusan-pdf',
        ]);

        $dir = storage_path('app/putusan_ma');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->filePath = $dir.DIRECTORY_SEPARATOR.md5($this->url).'.pdf';
        file_put_contents($this->filePath, '%PDF-1.4'.str_repeat('x', 600));
    }

    protected function tearDown(): void
    {
        @unlink($this->filePath);

        parent::tearDown();
    }

    private function download(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('legal-decisions.pdf', ['url' => $this->url, 'title' => 'Putusan Test', 'download' => 1]));
    }

    public function test_first_download_uploads_pdf_to_supabase_and_records_it(): void
    {
        Http::fake(['example.supabase.co/*' => Http::response(['Key' => 'ok'])]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();

        $objectPath = 'putusan_ma/'.md5($this->url).'.pdf';
        Http::assertSent(fn (Request $request): bool => $request->url() === "https://example.supabase.co/storage/v1/object/putusan-pdf/{$objectPath}"
            && $request->hasHeader('Authorization', 'Bearer test-key'));
        $this->assertSame("putusan-pdf/{$objectPath}", DownloadedLegalDecision::sole()->metadata['supabase_path']);
    }

    public function test_repeat_download_is_not_uploaded_or_recorded_again(): void
    {
        Http::fake(['example.supabase.co/*' => Http::response(['Key' => 'ok'])]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();
        $this->download(User::factory()->create(['role' => 'admin']))->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(1, DownloadedLegalDecision::count());
    }

    public function test_existing_record_without_supabase_path_is_uploaded_once_without_new_row(): void
    {
        Http::fake(['example.supabase.co/*' => Http::response(['Key' => 'ok'])]);
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::create(['source_url' => $this->url, 'decision_number' => 'X', 'title' => 'X', 'metadata' => ['ip' => '127.0.0.1']]);

        $this->download($admin)->assertOk();
        $this->download($admin)->assertOk();

        Http::assertSentCount(1);
        $record = DownloadedLegalDecision::sole();
        $this->assertSame('127.0.0.1', $record->metadata['ip']);
        $this->assertSame('putusan-pdf/putusan_ma/'.md5($this->url).'.pdf', $record->metadata['supabase_path']);
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

    public function test_supabase_failure_does_not_break_download(): void
    {
        Http::fake(['example.supabase.co/*' => Http::response('error', 500)]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertOk();

        $this->assertNull(DownloadedLegalDecision::sole()->metadata['supabase_path']);
    }

    public function test_preview_does_not_upload_or_record(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('legal-decisions.pdf', ['url' => $this->url]))->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, DownloadedLegalDecision::count());
    }
}
