<?php

namespace App\Services;

use App\Models\DownloadedLegalDecision;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Mengambil PDF putusan MA dari backend scraper dan mengarsipkannya ke Cloudflare R2.
 * Dipakai bersama oleh unduhan langsung, antrian bulk, dan perbaikan arsip lama
 * supaya aturan "hanya simpan putusan yang PDF-nya benar-benar ada" berlaku di semua jalur.
 */
class LegalDecisionPdfService
{
    /**
     * Path objek PDF di bucket R2 untuk satu URL putusan.
     */
    public function objectPath(string $url): string
    {
        return 'putusan_ma/'.md5($url).'.pdf';
    }

    /**
     * Klien HTTP ke backend scraper MA (ma-scraper-api/server.py), lokal atau lewat tunnel ngrok/Cloudflare.
     */
    public function scraperRequest(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.ma_scraper.url'), '/'))
            ->withHeaders([
                'X-API-Key' => (string) config('services.ma_scraper.token'),
                'ngrok-skip-browser-warning' => '1',
            ])
            ->timeout(60);
    }

    /**
     * Ambil PDF putusan dari backend scraper beserta alasan yang bisa dibaca user jika gagal.
     *
     * @return array{content: ?string, error: ?string}
     */
    public function fetch(string $url): array
    {
        if (! config('services.ma_scraper.url')) {
            return ['content' => null, 'error' => 'Layanan scraper PDF belum dikonfigurasi (MA_SCRAPER_URL).'];
        }

        try {
            // Tunnel ngrok/Cloudflare ke scraper kadang memutus koneksi sesaat: coba ulang sebelum menyerah.
            $response = $this->scraperRequest()
                ->timeout(90)
                ->retry(3, 1000, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->get('/pdf', ['url' => $url]);
        } catch (ConnectionException) {
            return ['content' => null, 'error' => 'Koneksi ke layanan scraper terputus atau timeout (tunnel ngrok mati/lambat). Coba lagi beberapa saat lagi.'];
        }

        $scraperMessage = trim((string) ($response->json('error') ?? $response->json('message') ?? ''));
        $detail = $scraperMessage !== '' ? " ({$scraperMessage})" : '';

        if (in_array($response->status(), [401, 403], true)) {
            return ['content' => null, 'error' => 'Layanan scraper menolak akses: API key (MA_SCRAPER_TOKEN) tidak valid.'];
        }

        if ($response->status() === 404) {
            return ['content' => null, 'error' => 'File PDF putusan tidak ditemukan di portal Mahkamah Agung.'.$detail];
        }

        if (! $response->successful()) {
            return ['content' => null, 'error' => "Scraper gagal mengambil PDF dari portal Mahkamah Agung (HTTP {$response->status()}).".$detail];
        }

        $body = $response->body();
        if (strlen($body) <= 500 || ! str_starts_with(ltrim($body), '%PDF')) {
            return ['content' => null, 'error' => 'Portal Mahkamah Agung tidak mengembalikan file PDF yang valid (putusan mungkin belum memiliki lampiran PDF).'];
        }

        return ['content' => $body, 'error' => null];
    }

    /**
     * Ambil PDF lalu simpan ke R2. Gagal (error terisi) jika PDF tidak didapat, atau R2 aktif tetapi upload gagal.
     *
     * @return array{content: ?string, r2_path: ?string, error: ?string}
     */
    public function archive(string $url): array
    {
        ['content' => $content, 'error' => $error] = $this->fetch($url);
        if ($content === null) {
            return ['content' => null, 'r2_path' => null, 'error' => $error];
        }

        $r2Path = $this->uploadToR2($content, $this->objectPath($url));
        if ($r2Path === null && $this->isR2Configured()) {
            return ['content' => $content, 'r2_path' => null, 'error' => 'PDF berhasil diambil, tetapi gagal disimpan ke Cloudflare R2. Periksa kredensial R2 lalu coba lagi.'];
        }

        return ['content' => $content, 'r2_path' => $r2Path, 'error' => null];
    }

    /**
     * Cek langsung ke Cloudflare R2 apakah file PDF putusan benar-benar ada.
     * Null jika R2 belum dikonfigurasi atau tidak bisa dihubungi (status tidak diketahui).
     * Hasil "ada" di-cache sehari; hasil "tidak ada" selalu dicek ulang agar cepat terlihat setelah diunduh ulang.
     */
    public function existsInR2(string $url): ?bool
    {
        if (! $this->isR2Configured()) {
            return null;
        }

        $cacheKey = 'legal-decision-r2-exists:'.md5($url);
        if (Cache::get($cacheKey)) {
            return true;
        }

        try {
            $exists = Storage::disk('r2')->exists($this->objectPath($url));
        } catch (\Throwable $e) {
            Log::channel('single')->warning('R2 exists check error: '.$e->getMessage());

            return null;
        }

        if ($exists) {
            Cache::put($cacheKey, true, now()->addDay());
        }

        return $exists;
    }

    /**
     * Catat putusan yang PDF-nya gagal diunduh ke arsip (tanpa file) supaya tetap tampil di Putusan MA (download)
     * dengan peringatan "File gagal terunduh" dan bisa diunduh ulang. Record yang sudah ada tidak diduplikasi.
     *
     * @param  array{decision_number?: string|null, court_name?: string|null, classification?: string|null, year?: mixed, summary?: string|null, tgl_register?: string|null, tgl_putus?: string|null, tgl_upload?: string|null}  $data
     */
    public function recordFailedDownload(string $url, array $data, ?int $userId, string $error, string $downloadType = 'manual'): DownloadedLegalDecision
    {
        $existing = DownloadedLegalDecision::query()->where('source_url', $url)->first();
        if ($existing) {
            $existing->update(['metadata' => [...($existing->metadata ?? []), 'download_error' => $error]]);

            return $existing;
        }

        $title = trim((string) ($data['decision_number'] ?? '')) ?: 'Putusan_MA_'.md5($url);
        $value = fn (string $key): ?string => trim((string) ($data[$key] ?? '')) ?: null;

        return DownloadedLegalDecision::create([
            'user_id' => $userId,
            'decision_number' => $title,
            'title' => $title,
            'court_name' => $value('court_name') ?? 'Mahkamah Agung',
            'classification' => $value('classification'),
            'year' => $value('year'),
            'tgl_register' => $value('tgl_register'),
            'tgl_putus' => $value('tgl_putus'),
            'tgl_upload' => $value('tgl_upload'),
            'summary' => $value('summary'),
            'source_url' => $url,
            'file_name' => trim((string) preg_replace('/\s+/', '_', (string) preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $title))).'.pdf',
            'file_path' => $this->objectPath($url),
            'file_size' => null,
            'download_type' => $downloadType,
            'metadata' => ['r2_path' => null, 'download_error' => $error],
        ]);
    }

    /**
     * Apakah arsip putusan sudah lengkap (record ada dan file PDF-nya benar-benar tersimpan di R2).
     * Record dengan r2_path di metadata tetapi file-nya hilang di R2 dianggap belum lengkap agar bisa diunduh ulang.
     */
    public function isArchiveComplete(?DownloadedLegalDecision $decision): bool
    {
        if ($decision === null) {
            return false;
        }

        if (! $this->isR2Configured()) {
            return empty($decision->metadata['download_error']);
        }

        if (empty($decision->metadata['r2_path'])) {
            return false;
        }

        return $this->existsInR2((string) $decision->source_url) !== false;
    }

    /**
     * Apakah disk Cloudflare R2 sudah dikonfigurasi lengkap.
     */
    public function isR2Configured(): bool
    {
        return (string) config('filesystems.disks.r2.bucket') !== ''
            && config('filesystems.disks.r2.endpoint')
            && config('filesystems.disks.r2.key');
    }

    /**
     * Hapus file PDF dari Cloudflare R2 dan bersihkan cache existence-nya.
     */
    public function deleteFromR2(string $url): bool
    {
        $path = $this->objectPath($url);
        $cacheKey = 'legal-decision-r2-exists:'.md5($url);
        Cache::forget($cacheKey);

        if (! $this->isR2Configured()) {
            return false;
        }

        try {
            if (Storage::disk('r2')->exists($path)) {
                return Storage::disk('r2')->delete($path);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning("R2 delete error for {$path}: ".$e->getMessage());
        }

        return false;
    }

    /**
     * Upload PDF ke Cloudflare R2 (disk "r2"). Mengembalikan "bucket/path" jika berhasil, null jika gagal atau belum dikonfigurasi.
     */
    public function uploadToR2(string $content, string $objectPath): ?string
    {
        if (! $this->isR2Configured()) {
            return null;
        }

        $bucket = (string) config('filesystems.disks.r2.bucket');

        try {
            if (Storage::disk('r2')->put($objectPath, $content, ['ContentType' => 'application/pdf'])) {
                return "{$bucket}/{$objectPath}";
            }

            Log::channel('single')->warning("R2 upload error: gagal menulis {$objectPath}");
        } catch (\Throwable $e) {
            Log::channel('single')->warning('R2 upload error: '.$e->getMessage());
        }

        return null;
    }
}
