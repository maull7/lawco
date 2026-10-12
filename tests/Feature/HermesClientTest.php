<?php

namespace Tests\Feature;

use App\Services\AiClient;
use App\Services\HermesClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HermesClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.openai.api_key' => 'openai-test',
            'ai.openai.base_url' => 'https://openai.test/v1',
            'ai.groq.api_key' => null,
            'hermes.base_url' => 'http://hermes.test/v1',
            'hermes.token' => 'hermes-test',
            'hermes.poll_seconds' => 0,
        ]);
        Http::preventStrayRequests();
    }

    private function payload(bool $json = false): array
    {
        return [
            'messages' => [['role' => 'system', 'content' => 'Analisis hanya dokumen ini.'], ['role' => 'user', 'content' => 'Konten dokumen.']],
            'max_tokens' => 100,
            ...($json ? ['response_format' => ['type' => 'json_object']] : []),
        ];
    }

    public function test_successful_openai_request_does_not_call_hermes(): void
    {
        Http::fake(['https://openai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Jawaban OpenAI']]],
            'usage' => ['total_tokens' => 50],
        ])]);

        $result = app(AiClient::class)->complete($this->payload());
        $this->assertSame('openai', $result['provider']);
        $this->assertSame(50, $result['total_tokens']);
        Http::assertSentCount(1);
    }

    #[DataProvider('creditErrors')]
    public function test_credit_exhaustion_falls_back_to_hermes(int $status, array $error): void
    {
        Http::fake([
            'https://openai.test/v1/chat/completions' => Http::response(['error' => $error], $status),
            'http://hermes.test/v1/runs' => Http::response(['run_id' => 'run-1', 'status' => 'queued'], 202),
            'http://hermes.test/v1/runs/run-1' => Http::sequence()
                ->push(['status' => 'running'])
                ->push(['status' => 'completed', 'output' => 'Jawaban Hermes', 'usage' => ['input_tokens' => 20, 'output_tokens' => 10]]),
        ]);

        $result = app(AiClient::class)->complete($this->payload());
        $this->assertSame('hermes', $result['provider']);
        $this->assertSame('Jawaban Hermes', $result['content']);
        $this->assertSame(30, $result['total_tokens']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://hermes.test/v1/runs'
            && $request->hasHeader('Authorization', 'Bearer hermes-test')
            && str_starts_with($request['session_id'], 'lawco-')
            && str_contains($request['input'], 'Analisis hanya dokumen ini.')
            && str_contains($request['input'], 'Konten dokumen.'));
        Http::assertSentCount(4);
    }

    public static function creditErrors(): array
    {
        return [
            'quota' => [429, ['code' => 'insufficient_quota']],
            'type despite other code' => [429, ['code' => 'quota_error', 'type' => 'insufficient_quota']],
            'hard limit' => [429, ['code' => 'billing_hard_limit_reached']],
            'billing inactive' => [403, ['type' => 'billing_not_active']],
            'payment required' => [402, ['message' => 'Payment required']],
        ];
    }

    public function test_rate_limit_does_not_trigger_credit_fallback(): void
    {
        Http::fake(['https://openai.test/v1/chat/completions' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429)]);
        try {
            app(AiClient::class)->complete($this->payload());
            $this->fail('Expected rate-limit error.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 429', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_missing_openai_key_uses_hermes_and_json_output_is_normalized(): void
    {
        config(['ai.openai.api_key' => null]);
        Http::fake(['http://hermes.test/v1/runs' => Http::response(['output' => "```json\n{\"status\":\"ok\"}\n```"])]);
        $result = app(AiClient::class)->complete($this->payload(json: true));
        $this->assertSame(['status' => 'ok'], json_decode($result['content'], true));
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidResponses')]
    public function test_gateway_errors_and_invalid_outputs_are_rejected(array|string $body, int $status, string $message): void
    {
        Http::fake(['http://hermes.test/v1/runs' => Http::response($body, $status)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        app(HermesClient::class)->complete($this->payload(json: true));
    }

    public static function invalidResponses(): array
    {
        return [
            'unauthorized' => [[], 401, 'HTTP 401'],
            'failed run' => [['status' => 'failed', 'error' => 'secret error'], 200, 'gagal menyelesaikan'],
            'revoked auth' => [['status' => 'failed', 'error' => 'token_revoked HTTP 401'], 200, 'Login ulang'],
            'empty output' => [['status' => 'completed', 'output' => ''], 200, 'tidak mengembalikan jawaban'],
            'invalid json' => [['output' => 'not json'], 200, 'objek JSON valid'],
            'bad envelope' => ['not JSON', 200, 'Respons Hermes tidak valid'],
            'invalid id' => [['run_id' => []], 200, 'ID run Hermes tidak valid'],
        ];
    }

    public function test_failed_polling_is_reported(): void
    {
        Http::fake([
            'http://hermes.test/v1/runs' => Http::response(['run_id' => 'one']),
            'http://hermes.test/v1/runs/one' => Http::response([], 503),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('status Hermes (HTTP 503)');
        app(HermesClient::class)->complete($this->payload());
    }

    public function test_a_failed_run_does_not_fall_back_to_openai_again(): void
    {
        Http::fake([
            'https://openai.test/v1/chat/completions' => Http::response(['error' => ['code' => 'insufficient_quota']], 429),
            'http://hermes.test/v1/runs' => Http::response(['run_id' => 'one']),
            'http://hermes.test/v1/runs/one' => Http::response(['status' => 'failed']),
        ]);
        try {
            app(AiClient::class)->complete($this->payload());
            $this->fail('Expected Hermes failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('gagal menyelesaikan', $exception->getMessage());
        }
        Http::assertSentCount(3);
    }

    public function test_timeout_is_bounded(): void
    {
        config(['hermes.run_timeout' => 1, 'hermes.poll_seconds' => 1]);
        Http::fake([
            'http://hermes.test/v1/runs' => Http::response(['run_id' => 'one']),
            'http://hermes.test/v1/runs/one' => Http::response(['status' => 'running']),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('batas waktu');
        app(HermesClient::class)->complete($this->payload());
    }

    public function test_missing_token_sends_no_requests(): void
    {
        config(['hermes.token' => null]);
        Http::fake();
        try {
            app(HermesClient::class)->complete($this->payload());
            $this->fail('Expected configuration error.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('belum dikonfigurasi', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_connection_failure_is_not_retried_as_a_new_run(): void
    {
        Http::fake(['http://hermes.test/v1/runs' => Http::failedConnection()]);
        $this->expectException(ConnectionException::class);
        app(HermesClient::class)->complete($this->payload());
    }

    public function test_check_command_simulates_openai_quota_and_calls_hermes(): void
    {
        Http::fake(['http://hermes.test/v1/runs' => Http::response(['output' => ['status' => 'ok']])]);
        $this->artisan('hermes:check', ['--fallback' => true])
            ->expectsOutputToContain('menyelesaikan permintaan')->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://hermes.test/v1/runs');
    }

    public function test_check_command_checks_a_real_completion_contract(): void
    {
        Http::fake(['http://hermes.test/v1/runs' => Http::response(['output' => ['status' => 'ok']])]);
        $this->artisan('hermes:check')->expectsOutputToContain('menyelesaikan permintaan')->assertSuccessful();
    }
}
