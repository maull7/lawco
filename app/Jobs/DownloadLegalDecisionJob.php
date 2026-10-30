<?php

namespace App\Jobs;

use App\Models\DownloadedLegalDecision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
    public function handle(): void
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

        // Ambil PDF dari scraper jika belum ada di R2 / lokal
        $content = null;
        if (! $existing || empty($existing->metadata['r2_path'])) {
            $content = $this->fetchPdfContent($url);
        }

        $r2Path = null;
        if ($content !== null) {
            $r2Path = $this->uploadToR2($content, 'putusan_ma/'.$hash.'.pdf');
        }

        if ($existing) {
            $updates = [];
            if (empty($existing->classification) && $classification !== '') {
                $updates['classification'] = $classification;
            }
            if ((empty($existing->court_name) || $existing->court_name === 'Mahkamah Agung') && $courtName !== 'Mahkamah Agung') {
                $updates['court_name'] = $courtName;
            }
            if (empty($existing->year) && $year !== '') {
                $updates['year'] = $year;
            }
            if (empty($existing->summary) && $summary !== '') {
                $updates['summary'] = $summary;
            }
            if (empty($existing->tgl_register) && $tglRegister !== '') {
                $updates['tgl_register'] = $tglRegister;
            }
            if (empty($existing->tgl_putus) && $tglPutus !== '') {
                $updates['tgl_putus'] = $tglPutus;
            }
            if (empty($existing->tgl_upload) && $tglUpload !== '') {
                $updates['tgl_upload'] = $tglUpload;
            }
            if (empty($existing->metadata['r2_path']) && $r2Path !== null) {
                $updates['metadata'] = [
                    ...($existing->metadata ?? []),
                    'r2_path' => $r2Path,
                ];
            }
            if ($existing->file_size === null && $content !== null) {
                $updates['file_size'] = strlen($content);
            }

            if (! empty($updates)) {
                $existing->update($updates);
            }

            return;
        }

        try {
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
                'file_path' => 'putusan_ma/'.$hash.'.pdf',
                'file_size' => $content !== null ? strlen($content) : null,
                'mime_type' => 'application/pdf',
                'download_type' => 'bulk',
                'metadata' => [
                    'queued_at' => now()->toISOString(),
                    'r2_path' => $r2Path,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error("[DownloadLegalDecisionJob] Gagal menyimpan DownloadedLegalDecision: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Ambil konten biner PDF dari microservice scraper MA.
     */
    private function fetchPdfContent(string $url): ?string
    {
        $scraperUrl = config('services.ma_scraper.url');
        if (! $scraperUrl) {
            Log::channel('single')->warning('[DownloadLegalDecisionJob] MA_SCRAPER_URL belum dikonfigurasi.');

            return null;
        }

        try {
            $response = Http::baseUrl(rtrim((string) $scraperUrl, '/'))
                ->withHeaders([
                    'X-API-Key' => (string) config('services.ma_scraper.token'),
                    'ngrok-skip-browser-warning' => '1',
                ])
                ->timeout(120)
                ->get('/pdf', ['url' => $url]);

            if ($response->successful() && strlen($response->body()) > 500) {
                return $response->body();
            }

            Log::channel('single')->warning("[DownloadLegalDecisionJob] Respons PDF tidak valid (HTTP {$response->status()}): ".substr($response->body(), 0, 200));
        } catch (ConnectionException $e) {
            Log::channel('single')->error("[DownloadLegalDecisionJob] Scraper unreachable: {$e->getMessage()}");
            throw $e;
        }

        return null;
    }

    /**
     * Salin PDF ke Cloudflare R2 jika storage disk r2 dikonfigurasi.
     */
    private function uploadToR2(string $content, string $objectPath): ?string
    {
        $bucket = (string) config('filesystems.disks.r2.bucket');
        if ($bucket === '' || ! config('filesystems.disks.r2.endpoint') || ! config('filesystems.disks.r2.key')) {
            return null;
        }

        try {
            Storage::disk('r2')->put($objectPath, $content, ['ContentType' => 'application/pdf']);

            return "{$bucket}/{$objectPath}";
        } catch (\Throwable $e) {
            Log::channel('single')->warning("[DownloadLegalDecisionJob] R2 upload error: {$e->getMessage()}");
        }

        return null;
    }
}
