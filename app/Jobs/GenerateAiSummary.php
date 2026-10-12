<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\ReviewDocument;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(
        public ReviewDocument $document,
        public string $type,
    ) {}

    public function handle(AiService $aiService): void
    {
        $action = 'summary:'.$this->type;
        AiJobStatus::begin($this->document, $action);

        try {
            $aiService->generateSummary($this->document, $this->type);
            $this->document->aiStatus($action)?->markDone("Summary {$this->type} selesai.");
        } catch (\Throwable $e) {
            report($e);
            $this->document->aiStatus($action)?->markFailed('Gagal generate summary: '.$e->getMessage());

            throw $e;
        }
    }
}
