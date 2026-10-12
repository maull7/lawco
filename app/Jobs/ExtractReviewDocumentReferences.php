<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\ReviewDocument;
use App\Services\ReviewDocumentReferencesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractReviewDocumentReferences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'ai';

    public $timeout = 900;

    public $tries = 2;

    public function __construct(
        public ReviewDocument $document,
    ) {}

    public function handle(ReviewDocumentReferencesService $referencesService): void
    {
        AiJobStatus::begin($this->document, 'extract');

        $result = $referencesService->extract($this->document);

        if ($result['success']) {
            $this->document->aiStatus('extract')?->markDone($result['message']);
        } else {
            $this->document->aiStatus('extract')?->markFailed($result['message']);
        }
    }
}
