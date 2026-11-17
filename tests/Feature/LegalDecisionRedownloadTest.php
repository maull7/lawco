<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use App\Services\LegalDecisionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDecisionRedownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/redownload.html';

    private function failedRecordOf(User $user): DownloadedLegalDecision
    {
        return DownloadedLegalDecision::create([
            'user_id' => $user->id,
            'source_url' => $this->url,
            'decision_number' => '12/Pdt.G/2024',
            'title' => '12/Pdt.G/2024',
            'download_type' => 'bulk',
            'metadata' => ['download_error' => 'Portal MA tidak mengembalikan PDF', 'queued_at' => null],
        ]);
    }

    public function test_failed_decision_redownloaded_by_other_user_stays_with_owner_and_appears_in_their_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['access_legal_decisions']]);
        $owner = User::factory()->create(['name' => 'Zaky']);
        $redownloader = User::factory()->create(['name' => 'Bondan']);
        $other = User::factory()->create();
        $this->failedRecordOf($owner);

        $record = app(LegalDecisionPdfService::class)->recordQueuedDownload($this->url, [], $redownloader->id);

        $this->assertSame($owner->id, $record->fresh()->user_id);
        $this->assertSame($redownloader->id, $record->metadata['redownloaded_by_user_id']);
        $this->assertSame('Bondan', $record->metadata['redownloaded_by_name']);

        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['user_id' => $redownloader->id]))
            ->assertOk()->assertSeeText('12/Pdt.G/2024')->assertSeeText('Diunduh oleh Zaky')->assertSeeText('Diunduh ulang oleh Bondan');
        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['user_id' => $owner->id]))
            ->assertOk()->assertSeeText('12/Pdt.G/2024');
        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['user_id' => $other->id]))
            ->assertOk()->assertDontSeeText('12/Pdt.G/2024');
    }

    public function test_owner_is_kept_when_redownload_completes(): void
    {
        $owner = User::factory()->create();
        $redownloader = User::factory()->create();
        $service = app(LegalDecisionPdfService::class);
        $this->failedRecordOf($owner);

        $record = $service->recordQueuedDownload($this->url, [], $redownloader->id);

        $this->assertSame($owner->id, $service->downloadOwnerId($record->user_id, $record->metadata, $redownloader->id));
    }

    public function test_latest_redownloader_is_shown_after_repeated_failures(): void
    {
        $owner = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create(['name' => 'Nindia']);
        $this->failedRecordOf($owner);
        $service = app(LegalDecisionPdfService::class);

        $service->recordQueuedDownload($this->url, [], $second->id);
        $service->recordFailedDownload($this->url, [], $second->id, 'gagal lagi', 'bulk');
        $record = $service->recordQueuedDownload($this->url, [], $third->id);

        $this->assertSame($owner->id, $record->fresh()->user_id);
        $this->assertSame('Nindia', $record->metadata['redownloaded_by_name']);
    }

    public function test_same_user_retry_is_not_marked_as_redownload(): void
    {
        $user = User::factory()->create();
        $this->failedRecordOf($user);

        $record = app(LegalDecisionPdfService::class)->recordQueuedDownload($this->url, [], $user->id);

        $this->assertArrayNotHasKey('redownloaded_by_user_id', $record->metadata);
    }

    public function test_decision_still_in_queue_is_not_marked_as_redownload(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        DownloadedLegalDecision::create([
            'user_id' => $first->id,
            'source_url' => $this->url,
            'decision_number' => '12/Pdt.G/2024',
            'title' => '12/Pdt.G/2024',
            'metadata' => ['queued_at' => now()->toISOString()],
        ]);

        $record = app(LegalDecisionPdfService::class)->recordQueuedDownload($this->url, [], $second->id);

        $this->assertArrayNotHasKey('redownloaded_by_user_id', $record->metadata);
    }

    public function test_redownloaded_by_filter_shows_only_matching_redownloads(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['access_legal_decisions']]);
        $owner = User::factory()->create();
        $bondan = User::factory()->create(['name' => 'Bondan']);
        $nindia = User::factory()->create(['name' => 'Nindia']);
        $service = app(LegalDecisionPdfService::class);
        $this->failedRecordOf($owner);
        $service->recordQueuedDownload($this->url, [], $bondan->id);
        DownloadedLegalDecision::create(['user_id' => $owner->id, 'source_url' => $this->url.'?biasa', 'decision_number' => '99/Pid/2023', 'title' => '99/Pid/2023']);

        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['redownloaded_by' => $bondan->id]))
            ->assertOk()->assertSeeText('12/Pdt.G/2024')->assertDontSeeText('99/Pid/2023')->assertSeeText('Diunduh ulang oleh Bondan');
        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['redownloaded_by' => $nindia->id]))
            ->assertOk()->assertDontSeeText('12/Pdt.G/2024');
        $this->actingAs($admin)->get(route('legal-decisions.downloads', ['redownloaded_by' => 'any']))
            ->assertOk()->assertSeeText('12/Pdt.G/2024')->assertDontSeeText('99/Pid/2023');
    }

    public function test_non_admin_cannot_filter_other_users_redownloads(): void
    {
        $owner = User::factory()->create();
        $bondan = User::factory()->create();
        $subAdmin = User::factory()->create(['role' => 'sub_admin', 'permissions' => ['access_legal_decisions']]);
        $this->failedRecordOf($owner);
        app(LegalDecisionPdfService::class)->recordQueuedDownload($this->url, [], $bondan->id);
        DownloadedLegalDecision::create(['user_id' => $owner->id, 'source_url' => $this->url.'?biasa', 'decision_number' => '99/Pid/2023', 'title' => '99/Pid/2023']);

        // Filter ke pengguna lain diabaikan: semua putusan tetap tampil, bukan hasil filter orang lain.
        $this->actingAs($subAdmin)->get(route('legal-decisions.downloads', ['redownloaded_by' => $bondan->id]))
            ->assertOk()->assertSeeText('99/Pid/2023');
    }
}
