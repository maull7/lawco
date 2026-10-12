<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\LegalCase;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCaseAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(public LegalCase $case) {}

    public function handle(AiService $aiService): void
    {
        AiJobStatus::begin($this->case, 'analysis');

        try {
            $selected = $aiService->selectRelevantRegulations($this->case);

            $sync = [];
            foreach ($selected as $regulationId => $explanation) {
                $sync[$regulationId] = ['explanation' => $explanation];
            }

            $this->case->regulations()->sync($sync);

            $analysis = $aiService->analyzeCase($this->case);
            $this->case->update([
                'analysis' => $analysis,
                'analyzed_at' => now(),
            ]);
            $this->case->aiStatus('analysis')?->markDone('Analisa kasus selesai.');
        } catch (\Throwable $e) {
            report($e);
            $this->case->aiStatus('analysis')?->markFailed('Gagal analisa kasus: '.$e->getMessage());

            throw $e;
        }
    }
}
