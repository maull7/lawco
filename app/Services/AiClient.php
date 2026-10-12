<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiClient
{
    public function __construct(private readonly HermesClient $hermes) {}

    /**
     * @param  array{messages: array<int, array{role: string, content: string}>, max_tokens?: int, temperature?: float, response_format?: array{type: string}}  $payload
     * @return array{content: string, provider: string, model: string, total_tokens: int}
     */
    public function complete(array $payload, bool $allowGroq = false): array
    {
        if (! config('ai.openai.api_key')) {
            if (! config('hermes.token') && $allowGroq && config('ai.groq.api_key')) {
                return $this->normalize($this->request('groq', $payload), 'groq');
            }

            return $this->hermes->complete($payload);
        }

        try {
            $response = $this->request('openai', $payload);
        } catch (Throwable $exception) {
            if ($allowGroq && config('ai.groq.api_key')) {
                return $this->normalize($this->request('groq', $payload), 'groq');
            }
            throw $exception;
        }

        if ($this->creditExhausted($response)) {
            Log::notice('OpenAI credit exhausted; using Hermes fallback.');

            return $this->hermes->complete($payload);
        }
        if (! $response->successful() && $allowGroq && config('ai.groq.api_key')) {
            return $this->normalize($this->request('groq', $payload), 'groq');
        }

        return $this->normalize($response, 'openai');
    }

    /** @param array<string, mixed> $payload */
    private function request(string $provider, array $payload): Response
    {
        return Http::withToken((string) config('ai.'.$provider.'.api_key'))
            ->acceptJson()->connectTimeout(10)->timeout(120)
            ->post(rtrim((string) config('ai.'.$provider.'.base_url'), '/').'/chat/completions', [
                ...$payload,
                'model' => config('ai.'.$provider.'.model'),
            ]);
    }

    private function creditExhausted(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }
        $codes = [$response->json('error.code'), $response->json('error.type')];

        return count(array_intersect(array_filter($codes, 'is_string'), ['insufficient_quota', 'billing_hard_limit_reached', 'billing_not_active', 'usage_limit_reached'])) > 0
            || $response->status() === 402;
    }

    /** @return array{content: string, provider: string, model: string, total_tokens: int} */
    private function normalize(Response $response, string $provider): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('Provider AI '.$provider.' gagal (HTTP '.$response->status().').');
        }
        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Provider AI '.$provider.' tidak mengembalikan jawaban.');
        }

        return [
            'content' => $content,
            'provider' => $provider,
            'model' => (string) ($response->json('model') ?? config('ai.'.$provider.'.model')),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
        ];
    }
}
