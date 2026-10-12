<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\Regulation;
use App\Services\RegulationReferencesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractRegulationReferences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(
        public Regulation $regulation,
    ) {}

    public function handle(RegulationReferencesService $referencesService): void
    {
        AiJobStatus::begin($this->regulation, 'extract');

        $result = $referencesService->extract($this->regulation);

        if ($result['success']) {
            $this->regulation->aiStatus('extract')?->markDone($result['message']);
        } else {
            $this->regulation->aiStatus('extract')?->markFailed($result['message']);
        }
    }
}
