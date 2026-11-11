<?php

namespace Tests\Feature;

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LegalDecisionDetailTest extends TestCase
{
    use RefreshDatabase;

    private string $url = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/test-detail.html';

    /** @var array{title: string, fields: list<array{label: string, value: string}>} */
    private array $detail = [
        'title' => 'Putusan MAHKAMAH AGUNG Nomor 817 K/PDT.SUS-PHI/2026',
        'fields' => [
            ['label' => 'Hakim Ketua', 'value' => 'Panji Widagdo'],
            ['label' => 'Amar', 'value' => 'Tolak'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ma_scraper.url' => 'https://scraper.test']);
    }

    public function test_detail_is_fetched_from_scraper_and_stored_on_downloaded_record(): void
    {
        Http::fake(['scraper.test/detail*' => Http::response(['success' => true, ...$this->detail])]);
        $admin = User::factory()->create(['role' => 'admin']);
        $record = DownloadedLegalDecision::query()->create([
            'user_id' => $admin->id,
            'decision_number' => '817 K/PDT.SUS-PHI/2026',
            'source_url' => $this->url,
            'metadata' => ['r2_path' => 'x.pdf'],
        ]);

        $this->actingAs($admin)->getJson(route('legal-decisions.detail', ['url' => $this->url]))
            ->assertOk()
            ->assertJsonPath('fields.0.value', 'Panji Widagdo');

        $this->assertSame($this->detail, $record->fresh()->metadata['ma_detail']);
        $this->assertSame('x.pdf', $record->fresh()->metadata['r2_path']);
    }

    public function test_stored_detail_is_served_without_calling_scraper(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        DownloadedLegalDecision::query()->create([
            'user_id' => $admin->id,
            'decision_number' => '817 K/PDT.SUS-PHI/2026',
            'source_url' => $this->url,
            'metadata' => ['ma_detail' => $this->detail],
        ]);

        $this->actingAs($admin)->getJson(route('legal-decisions.detail', ['url' => $this->url]))
            ->assertOk()
            ->assertJsonPath('fields.1.value', 'Tolak');

        Http::assertNothingSent();
    }

    public function test_scraper_failure_returns_error(): void
    {
        Http::fake(['scraper.test/detail*' => Http::response(['success' => false], 502)]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson(route('legal-decisions.detail', ['url' => $this->url]))
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_url_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson(route('legal-decisions.detail'))->assertUnprocessable();
    }

    public function test_guests_are_blocked(): void
    {
        $this->getJson(route('legal-decisions.detail', ['url' => $this->url]))->assertUnauthorized();
    }
}
