<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiClient;
use App\Services\LegalDecisionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LegalDecisionAiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_ai_chat_validates_empty_message(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('legal-decisions.ai-chat'), [
            'message' => '',
            'context_putusan' => [],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Pertanyaan tidak boleh kosong.',
            ]);
    }

    public function test_ai_chat_extracts_live_pdf_and_returns_ai_reply(): void
    {
        $sourceUrl = 'https://putusan3.mahkamahagung.go.id/direktori/putusan/sample123.html';

        $mockPdfService = Mockery::mock(LegalDecisionPdfService::class);
        $mockPdfService->shouldReceive('existsInR2')->andReturn(false);
        $mockPdfService->shouldReceive('fetch')->with($sourceUrl)->andReturn([
            'content' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
            'error' => null,
        ]);
        $this->app->instance(LegalDecisionPdfService::class, $mockPdfService);

        $mockAiClient = Mockery::mock(AiClient::class);
        $mockAiClient->shouldReceive('complete')->once()->withArgs(function ($payload) {
            $systemMsg = $payload['messages'][0]['content'] ?? '';

            return str_contains($systemMsg, '8604 K/PID.SUS/2026')
                && str_contains($systemMsg, 'RUANG LINGKUP EKSKLUSIF')
                && $payload['temperature'] === 0.2;
        })->andReturn([
            'content' => 'Amar putusan: Mengadili, menolak permohonan kasasi dari Pemohon Kasasi / Terdakwa.',
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'total_tokens' => 150,
        ]);
        $this->app->instance(AiClient::class, $mockAiClient);

        $response = $this->actingAs($this->admin())->postJson(route('legal-decisions.ai-chat'), [
            'message' => 'Jelaskan amar putusan ini',
            'context_putusan' => [
                'number' => 'Putusan Mahkamah Agung Nomor 8604 K/PID.SUS/2026',
                'court' => 'Mahkamah Agung',
                'classification' => 'Narkotika dan Psikotropika',
                'year' => '2026',
                'sourceUrl' => $sourceUrl,
            ],
            'history' => [],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'reply' => 'Amar putusan: Mengadili, menolak permohonan kasasi dari Pemohon Kasasi / Terdakwa.',
                'model' => 'gpt-4o-mini',
                'provider' => 'openai',
            ]);
    }

    public function test_ai_chat_includes_out_of_scope_guard_in_system_prompt(): void
    {
        $mockAiClient = Mockery::mock(AiClient::class);
        $mockAiClient->shouldReceive('complete')->once()->withArgs(function ($payload) {
            $systemMsg = $payload['messages'][0]['content'] ?? '';

            return str_contains($systemMsg, 'RUANG LINGKUP EKSKLUSIF (OUT-OF-SCOPE REFUSAL)')
                && str_contains($systemMsg, 'JIKA pengguna mengajukan pertanyaan di luar konteks perkara atau dokumen hukum ini');
        })->andReturn([
            'content' => 'Mohon maaf, saya adalah asisten TanyaPutusan AI yang khusus bertugas untuk menganalisis dokumen perkara ini.',
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'total_tokens' => 50,
        ]);
        $this->app->instance(AiClient::class, $mockAiClient);

        $response = $this->actingAs($this->admin())->postJson(route('legal-decisions.ai-chat'), [
            'message' => 'apa itu git push origin Tegar?',
            'context_putusan' => [
                'number' => 'Putusan Mahkamah Agung Nomor 8604 K/PID.SUS/2026',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'reply' => 'Mohon maaf, saya adalah asisten TanyaPutusan AI yang khusus bertugas untuk menganalisis dokumen perkara ini.',
            ]);
    }
}
