<?php

namespace App\Console\Commands;

use App\Services\AiClient;
use App\Services\HermesClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('hermes:check {--fallback : Simulate exhausted OpenAI credit and verify the Hermes fallback}')]
#[Description('Verify Hermes authentication and a complete AI run through the configured gateway.')]
class HermesCheck extends Command
{
    public function handle(HermesClient $hermes, AiClient $ai): int
    {
        try {
            if ($this->option('fallback')) {
                config(['ai.openai.api_key' => 'lawco-fallback-check']);
                $endpoint = rtrim((string) config('ai.openai.base_url'), '/').'/chat/completions';
                Http::fake([$endpoint => Http::response(['error' => ['code' => 'insufficient_quota']], 429)]);
            }

            $payload = [
                'messages' => [['role' => 'user', 'content' => 'Uji koneksi Lawco. Kembalikan JSON persis {"status":"ok"}.']],
                'max_tokens' => 32,
                'response_format' => ['type' => 'json_object'],
            ];
            $result = $this->option('fallback') ? $ai->complete($payload) : $hermes->complete($payload);
            $output = json_decode($result['content'], true);
            if (($output['status'] ?? null) !== 'ok') {
                $this->error('Hermes merespons, tetapi hasil pemeriksaan tidak sesuai.');

                return self::FAILURE;
            }
            if ($result['provider'] !== 'hermes') {
                $this->error('Pemeriksaan tidak menggunakan provider Hermes.');

                return self::FAILURE;
            }
            $this->info('Hermes terhubung dan menyelesaikan permintaan AI Lawco.');
            $this->line('Provider: '.$result['provider'].'; model: '.$result['model'].'; tokens: '.$result['total_tokens']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Pemeriksaan Hermes gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
