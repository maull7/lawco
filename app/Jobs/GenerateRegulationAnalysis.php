<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\Regulation;
use App\Services\RegulationAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateRegulationAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(
        public Regulation $regulation,
        public bool $regenerate = false,
    ) {}

    public function handle(RegulationAnalysisService $analysisService): void
    {
        AiJobStatus::begin($this->regulation, 'analysis');

        try {
            $analysis = $this->regenerate
                ? $analysisService->regenerate($this->regulation)
                : $analysisService->generate($this->regulation);

            $this->regulation->aiStatus('analysis')?->markDone("Analisis regulasi {$this->regulation->regulation_number} selesai.");
        } catch (\Throwable $e) {
            report($e);
            $this->regulation->aiStatus('analysis')?->markFailed('Gagal generate analisis: '.$e->getMessage());

            throw $e;
        }
    }
}
