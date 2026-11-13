<?php

namespace App\Jobs;

use App\Models\DownloadedLegalDecision;
use App\Models\LegalDecisionDownloadFailure;
use App\Models\UserActivityLog;
use App\Services\LegalDecisionPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DownloadLegalDecisionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Jumlah percobaan maksimal jika koneksi jaringan / scraper gagal.
     */
    public int $tries = 3;

    /**
     * Timeout per job eksekusi (detik).
     */
    public int $timeout = 180;

    /**
     * Batas waktu percobaan (menggantikan $tries) agar penundaan karena portal membatasi akses tidak menghabiskan percobaan.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * Create a new job instance.
     *
     * @param  array{
     *     url: string,
     *     decision_number?: string,
     *     court_name?: string,
     *     classification?: string,
     *     year?: string|int|null,
     *     summary?: string,
     *     tgl_register?: string,
     *     tgl_putus?: string,
     *     tgl_upload?: string
     * }  $decisionData
     */
    public function __construct(
        public array $decisionData,
        public ?int $userId = null
    ) {
        $this->onQueue('ma-download');
    }

    /**
     * Execute the job.
     */
    public function handle(LegalDecisionPdfService $pdfService): void
    {
        $url = trim($this->decisionData['url'] ?? '');
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            Log::channel('single')->warning('[DownloadLegalDecisionJob] URL tidak valid, dilewati: '.json_encode($this->decisionData));

            return;
        }

        $hash = md5($url);
        $customTitle = trim((string) ($this->decisionData['decision_number'] ?? ''));
        $safeName = $customTitle !== '' ? preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $customTitle) : 'Putusan_MA_'.$hash;
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)).'.pdf';

        $courtName = trim((string) ($this->decisionData['court_name'] ?? 'Mahkamah Agung')) ?: 'Mahkamah Agung';
        $classification = trim((string) ($this->decisionData['classification'] ?? ''));
        $year = trim((string) ($this->decisionData['year'] ?? ''));
        $summary = trim((string) ($this->decisionData['summary'] ?? ''));
        $tglRegister = trim((string) ($this->decisionData['tgl_register'] ?? ''));
        $tglPutus = trim((string) ($this->decisionData['tgl_putus'] ?? ''));
        $tglUpload = trim((string) ($this->decisionData['tgl_upload'] ?? ''));

        $existing = DownloadedLegalDecision::query()->where('source_url', $url)->first();
        if ($pdfService->isArchiveComplete($existing)) {
            return;
        }

        // Record hanya disimpan jika PDF benar-benar didapat, supaya arsip tidak berisi putusan yang preview-nya 404.
        // Portal MA sedang membatasi akses: tunda job sampai masa tunggu habis, jangan dicatat sebagai gagal.
        if ($blockedUntil = $pdfService->sourceBlockedUntil()) {
            $this->release(max(5, (int) ceil(now()->diffInSeconds($blockedUntil))));

            return;
        }

        ['content' => $content, 'r2_path' => $r2Path, 'error' => $error] = $pdfService->archive($url);
        if ($error !== null && ($blockedUntil = $pdfService->sourceBlockedUntil())) {
            $this->release(max(5, (int) ceil(now()->diffInSeconds($blockedUntil))));

            return;
        }

        if ($pdfService->isTransientError($error)) {
            $this->release(LegalDecisionPdfService::TIMEOUT_RETRY_SECONDS);

            return;
        }

        if ($error !== null) {
            Log::channel('single')->warning("[DownloadLegalDecisionJob] {$url}: {$error}");

            // Tetap masuk ke Putusan MA (download) dengan peringatan "File gagal terunduh".
            $failedRecord = $pdfService->recordFailedDownload($url, $this->decisionData, $this->userId, $error, 'bulk');

            LegalDecisionDownloadFailure::recordFailure(
                url: $url,
                errorMessage: $error,
                decisionNumber: $customTitle ?: $safeName,
                userId: $this->userId
            );

            UserActivityLog::log(
                action: 'download_failed',
                subjectType: DownloadedLegalDecision::class,
                subjectId: $failedRecord->id,
                description: 'Gagal mengunduh Putusan MA (bulk): '.($customTitle ?: $safeName).' ('.$error.')',
                userId: $this->userId
            );

            Cache::put('legal-decision-download-failed:'.md5($url), [
                'error' => $error,
                'failed_at' => now()->toISOString(),
            ], now()->addDays(7));

            $this->fail(new RuntimeException("Gagal mengunduh putusan {$url}: {$error}"));

            return;
        }

        LegalDecisionDownloadFailure::clearFailure($url, $this->userId);
        Cache::forget('legal-decision-download-failed:'.md5($url));

        // Putusan lama tanpa PDF di R2: lengkapi arsipnya, bukan membuat record baru.
        if ($existing) {
            $resolvedYear = $pdfService->resolveDecisionYear($year ?: $existing->year, $existing->decision_number ?: $customTitle, $tglPutus ?: $existing->tgl_putus, $tglRegister ?: $existing->tgl_register, $tglUpload ?: $existing->tgl_upload);
            $existing->update([
                'user_id' => $this->userId ?? $existing->user_id,
                'year' => $resolvedYear ?: $existing->year,
                'file_size' => strlen($content),
                'metadata' => [...($existing->metadata ?? []), ...$pdfService->integrityMetadata($content, $r2Path)],
            ]);

            UserActivityLog::log(
                action: 'downloaded',
                subjectType: DownloadedLegalDecision::class,
                subjectId: $existing->id,
                description: "Mengunduh dan melengkapi berkas PDF Putusan MA (antrian): {$existing->decision_number}",
                userId: $this->userId
            );

            return;
        }

        $record = DownloadedLegalDecision::create([
            'user_id' => $this->userId,
            'decision_number' => $customTitle ?: $safeName,
            'title' => $customTitle ?: $safeName,
            'court_name' => $courtName,
            'classification' => $classification ?: null,
            'year' => $year ?: null,
            'tgl_register' => $tglRegister ?: null,
            'tgl_putus' => $tglPutus ?: null,
            'tgl_upload' => $tglUpload ?: null,
            'summary' => $summary ?: null,
            'source_url' => $url,
            'file_name' => $safeName,
            'file_path' => $pdfService->objectPath($url),
            'file_size' => strlen($content),
            'mime_type' => 'application/pdf',
            'download_type' => 'bulk',
            'metadata' => [
                'queued_at' => now()->toISOString(),
                ...$pdfService->integrityMetadata($content, $r2Path),
            ],
        ]);

        UserActivityLog::log(
            action: 'downloaded',
            subjectType: DownloadedLegalDecision::class,
            subjectId: $record->id,
            description: "Mengunduh Putusan MA (antrian bulk): {$record->decision_number}",
            userId: $this->userId
        );
    }

    /**
     * Job menyerah (batas waktu antrian habis atau error tak terduga): record yang masih "Dalam antrian" ditandai gagal
     * agar tidak menggantung selamanya dan bisa diunduh ulang.
     */
    public function failed(?\Throwable $exception): void
    {
        $url = trim($this->decisionData['url'] ?? '');
        $record = DownloadedLegalDecision::query()->where('source_url', $url)->first();
        if ($record === null || ! $record->isQueued()) {
            return;
        }

        app(LegalDecisionPdfService::class)->recordFailedDownload(
            $url,
            $this->decisionData,
            $this->userId,
            'Antrian download gagal diproses: '.($exception?->getMessage() ?: 'batas waktu antrian habis'),
            'bulk'
        );
    }
}
