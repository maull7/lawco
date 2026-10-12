<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\ReviewDocument;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePartitionAnalyses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(
        public ReviewDocument $document,
        public string $type,
        public ?array $partitionIds = [],
    ) {}

    public function handle(AiService $aiService): void
    {
        AiJobStatus::begin($this->document, 'partitions');

        try {
            $aiService->generateAllPartitionAnalyses($this->document, $this->type, $this->partitionIds);
            $this->document->aiStatus('partitions')?->markDone('Analisa AI per-partisi selesai.');
        } catch (\Throwable $e) {
            report($e);
            $this->document->aiStatus('partitions')?->markFailed('Gagal analisa AI: '.$e->getMessage());

            throw $e;
        }
    }
}
