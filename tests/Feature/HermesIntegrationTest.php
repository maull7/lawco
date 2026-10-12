<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Services\AiService;
use App\Services\RegulationAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HermesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.openai.api_key' => 'test-key',
            'ai.openai.base_url' => 'https://openai.test/v1',
            'hermes.base_url' => 'http://hermes.test/v1',
            'hermes.token' => 'test-token',
            'hermes.poll_seconds' => 0,
        ]);
        Http::preventStrayRequests();
    }

    private function fakeFallback(array|string $output): void
    {
        Http::fake([
            'https://openai.test/v1/chat/completions' => Http::response(['error' => ['code' => 'insufficient_quota']], 429),
            'http://hermes.test/v1/runs' => Http::response(['output' => $output, 'usage' => ['total_tokens' => 35]]),
        ]);
    }

    public function test_reference_extraction_uses_hermes_when_openai_credit_is_empty(): void
    {
        $service = app(RegulationAnalysisService::class);
        $references = ['referenced_regulations' => [['name' => 'UU Test', 'number' => '1', 'year' => 2026, 'relationship' => 'dirujuk']]];
        $this->fakeFallback($references);
        $this->assertSame($references, $service->extractRegulationsFromText('UU Test tahun 2026'));
    }

    public function test_date_extraction_uses_hermes_when_openai_credit_is_empty(): void
    {
        $service = app(RegulationAnalysisService::class);
        $dates = ['tanggal_tetapkan' => '2026-01-01', 'tanggal_diundangkan' => null];
        $this->fakeFallback($dates);
        $this->assertSame($dates, $service->extractDatesFromText('Ditetapkan 1 Januari 2026'));
    }

    public function test_chapter_analysis_uses_hermes(): void
    {
        $regulation = Regulation::factory()->create(['parsed_at' => now(), 'parsed_text' => "BAB I\nPasal 1\nIsi dokumen."]);
        $output = ['pasal_structure' => [['pasal' => 'Pasal 1', 'content' => 'Isi', 'type' => 'existing']], 'referenced_regulations' => [], 'insights' => 'Analisis Hermes'];
        $this->fakeFallback($output);
        $service = app(RegulationAnalysisService::class);
        $this->assertSame('Analisis Hermes', $service->analyzeBabByIndex($regulation, 0)['insights']);
        $this->assertSame(1, $service->analyzeByBabs($regulation)['babs'][0]['pasal_count']);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://hermes.test/v1/runs');
    }

    public function test_regulation_chat_preserves_hermes_provider_and_usage(): void
    {
        $regulation = Regulation::factory()->create(['parsed_at' => now(), 'parsed_text' => 'Isi regulasi untuk chat.']);
        $this->fakeFallback('Jawaban Hermes berdasarkan regulasi.');
        $result = app(AiService::class)->askRegulation($regulation, 'Apa isi peraturan?', []);
        $this->assertSame('hermes', $result['provider']);
        $this->assertSame(35, $result['total_tokens']);
        $this->assertSame('Jawaban Hermes berdasarkan regulasi.', $result['content']);
    }
}
