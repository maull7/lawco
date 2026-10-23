<?php

namespace App\Jobs;

use App\Models\AiJobStatus;
use App\Models\Regulation;
use App\Services\AutomaticReferenceExtractService;
use App\Services\RegulationReferencesService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AutoExtractRegulationReferences implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public int $regulationId)
    {
        $this->onQueue(config('automatic-parse.extract_queue'));
    }

    public function handle(RegulationReferencesService $referencesService): void
    {
        if (! $this->batch()) {
            return;
        }
        if ($this->batch()->cancelled()) {
            $this->record('cancelled', 'Batch dibatalkan.');

            return;
        }
        $regulation = Regulation::find($this->regulationId);
        if (! $regulation) {
            $this->record('skipped', 'Regulasi sudah dihapus.');

            return;
        }
        if ($regulation->parse_status !== 'complete') {
            $this->record('skipped', 'Regulasi belum diparse lengkap.');

            return;
        }

        $status = AiJobStatus::firstOrCreate(
            ['model_type' => $regulation->getMorphClass(), 'model_id' => $regulation->id, 'action' => 'extract'],
            ['status' => 'processing'],
        );
        $isOwnRetry = $this->attempts() > 1 && $status->status === 'processing';
        if (! $status->wasRecentlyCreated && ! $isOwnRetry) {
            $this->record('skipped', 'Peraturan terkait sudah diekstrak atau sedang diproses.');

            return;
        }
        $this->record('processing');

        $result = $referencesService->extract($regulation);
        if (! $result['success']) {
            $status->markFailed($result['message']);
            $this->record('failed', $result['message']);

            return;
        }

        $linked = $referencesService->linkExactMatches($regulation);
        $status->markDone($result['message'].' '.$linked.' peraturan terhubung otomatis.');
        $this->record('complete', references: $result['count'], linked: $linked);
    }

    private function record(string $status, ?string $error = null, ?int $references = null, ?int $linked = null): void
    {
        app(AutomaticReferenceExtractService::class)->recordDocumentResult($this->batchId, $this->regulationId, $status, $error, $references, $linked);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?: 'Proses ekstrak otomatis gagal.';
        $this->record('failed', $message);
        Regulation::find($this->regulationId)?->aiStatus('extract')?->markFailed(mb_substr($message, 0, 500));
    }
}
