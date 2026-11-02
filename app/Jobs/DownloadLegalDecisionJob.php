<?php

namespace App\Jobs;

use App\Models\DownloadedLegalDecision;
use App\Services\LegalDecisionPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    ) {}

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
        if ($existing && (! empty($existing->metadata['r2_path']) || ! $pdfService->isR2Configured())) {
            return;
        }

        // Record hanya disimpan jika PDF benar-benar didapat, supaya arsip tidak berisi putusan yang preview-nya 404.
        ['content' => $content, 'r2_path' => $r2Path, 'error' => $error] = $pdfService->archive($url);
        if ($error !== null) {
            Log::channel('single')->warning("[DownloadLegalDecisionJob] {$url}: {$error}");
            $this->fail(new RuntimeException("Gagal mengunduh putusan {$url}: {$error}"));

            return;
        }

        // Putusan lama tanpa PDF di R2: lengkapi arsipnya, bukan membuat record baru.
        if ($existing) {
            $existing->update([
                'file_size' => strlen($content),
                'metadata' => [...($existing->metadata ?? []), 'r2_path' => $r2Path],
            ]);

            return;
        }

        DownloadedLegalDecision::create([
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
                'r2_path' => $r2Path,
            ],
        ]);
    }
}
