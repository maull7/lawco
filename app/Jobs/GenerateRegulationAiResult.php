<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\AiPrompt;
use App\Models\Regulation;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateRegulationAiResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(
        public Regulation $regulation,
        public AiPrompt $prompt,
    ) {}

    public function handle(AiService $aiService): void
    {
        AiJobStatus::begin($this->regulation, 'regulation-ai');

        try {
            $aiService->generateRegulationPrompt($this->regulation, $this->prompt);
            $this->regulation->aiStatus('regulation-ai')?->markDone("Generate AI {$this->prompt->title} selesai.");
        } catch (\Throwable $e) {
            report($e);
            $this->regulation->aiStatus('regulation-ai')?->markFailed('Gagal generate AI: '.$e->getMessage());

            throw $e;
        }
    }
}
