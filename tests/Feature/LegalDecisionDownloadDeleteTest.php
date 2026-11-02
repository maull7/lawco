<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalDecisionDownloadDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'permissions' => ['access_legal_decisions' => true],
        ]);
    }

    private function configureR2(): void
    {
        config([
            'filesystems.disks.r2.bucket' => 'test-bucket',
            'filesystems.disks.r2.endpoint' => 'https://test.r2.cloudflarestorage.com',
            'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.secret' => 'test-secret',
        ]);
        Storage::fake('r2');
    }

    public function test_admin_can_delete_downloaded_decision(): void
    {
        $this->configureR2();
        $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-delete.html';
        Storage::disk('r2')->put('putusan_ma/'.md5($url).'.pdf', '%PDF-1.4 test');

        $decision = DownloadedLegalDecision::create([
            'user_id' => $this->admin()->id,
            'source_url' => $url,
            'decision_number' => 'Putusan Hapus',
            'title' => 'Putusan Hapus',
        ]);

        $response = $this->actingAs($this->admin())
            ->delete(route('legal-decisions.downloads.destroy', $decision->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('downloaded_legal_decisions', ['id' => $decision->id]);
        $this->assertFalse(Storage::disk('r2')->exists('putusan_ma/'.md5($url).'.pdf'));
    }

    public function test_admin_can_bulk_delete_downloaded_decisions(): void
    {
        $this->configureR2();
        $url1 = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/bulk1.html';
        $url2 = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/bulk2.html';

        Storage::disk('r2')->put('putusan_ma/'.md5($url1).'.pdf', '%PDF-1.4 1');
        Storage::disk('r2')->put('putusan_ma/'.md5($url2).'.pdf', '%PDF-1.4 2');

        $d1 = DownloadedLegalDecision::create([
            'user_id' => $this->admin()->id,
            'source_url' => $url1,
            'decision_number' => 'Bulk 1',
            'title' => 'Bulk 1',
        ]);

        $d2 = DownloadedLegalDecision::create([
            'user_id' => $this->admin()->id,
            'source_url' => $url2,
            'decision_number' => 'Bulk 2',
            'title' => 'Bulk 2',
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('legal-decisions.downloads.bulk-destroy'), [
                'ids' => [$d1->id, $d2->id],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('downloaded_legal_decisions', ['id' => $d1->id]);
        $this->assertDatabaseMissing('downloaded_legal_decisions', ['id' => $d2->id]);
        $this->assertFalse(Storage::disk('r2')->exists('putusan_ma/'.md5($url1).'.pdf'));
        $this->assertFalse(Storage::disk('r2')->exists('putusan_ma/'.md5($url2).'.pdf'));
    }

    public function test_downloads_page_renders_delete_button_and_does_not_render_download_button(): void
    {
        $decision = DownloadedLegalDecision::create([
            'user_id' => $this->admin()->id,
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/view-test.html',
            'decision_number' => 'Putusan View Test',
            'title' => 'Putusan View Test',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('legal-decisions.downloads'));

        $response->assertOk();
        $response->assertSee('Putusan View Test');
        $response->assertSee('Delete');
        $response->assertSee('Hapus Terpilih');
        $response->assertDontSee('>Download<', false);
    }
}
