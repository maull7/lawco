<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_recap_date_and_time_filter_also_filters_decision_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['access_legal_decisions']]);
        $make = function (string $number, string $updatedAtWib) use ($admin): void {
            $record = DownloadedLegalDecision::create([
                'user_id' => $admin->id,
                'decision_number' => $number,
                'title' => $number,
                'source_url' => 'https://putusan3.mahkamahagung.go.id/direktori/putusan/'.md5($number).'.html',
                'metadata' => ['r2_path' => 'putusan-pdf/'.md5($number).'.pdf'],
            ]);
            $record->timestamps = false;
            $record->forceFill(['updated_at' => Carbon::parse($updatedAtWib, 'Asia/Jakarta')->timezone(config('app.timezone'))])->save();
        };
        $make('Putusan Pagi 01', '2026-11-10 09:30');
        $make('Putusan Siang 02', '2026-11-10 13:30');
        $make('Putusan Besok 03', '2026-11-11 09:30');

        $this->actingAs($admin)
            ->get(route('legal-decisions.downloads', ['recap_from' => '2026-11-10', 'recap_to' => '2026-11-10', 'recap_time_from' => '09:00', 'recap_time_to' => '10:00']))
            ->assertOk()
            ->assertSee('Putusan Pagi 01')
            ->assertDontSee('Putusan Siang 02')
            ->assertDontSee('Putusan Besok 03');

        $this->actingAs($admin)
            ->get(route('legal-decisions.downloads'))
            ->assertSee('Putusan Pagi 01')
            ->assertSee('Putusan Siang 02')
            ->assertSee('Putusan Besok 03');
    }
}
