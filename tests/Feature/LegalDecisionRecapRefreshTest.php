<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDecisionRecapRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_recap_table_partial_via_ajax(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $userA = User::factory()->create(['name' => 'Budi Santoso']);

        DownloadedLegalDecision::query()->create([
            'user_id' => $userA->id,
            'decision_number' => '123/Pdt/2026',
            'title' => 'Putusan Budi',
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/123.html',
            'metadata' => ['r2_path' => 'putusan_ma/test.pdf'],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('legal-decisions.downloads.recap-table'), [
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Rata-rata (file/menit)');
        $response->assertSee('Berhasil Di-download');
        $response->assertSee('Jam Ini');
    }

    public function test_downloads_page_displays_refresh_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DownloadedLegalDecision::query()->create([
            'user_id' => $admin->id,
            'decision_number' => '456/Pdt/2026',
            'title' => 'Putusan Admin',
            'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/456.html',
            'metadata' => ['r2_path' => 'putusan_ma/admin-test.pdf'],
        ]);

        $response = $this->actingAs($admin)->get(route('legal-decisions.downloads'));

        $response->assertStatus(200);
        $response->assertSee('Rekap Hasil Download per Pengguna');
        $response->assertSee('refreshRecap');
        $response->assertSee('Refresh');
    }
}
