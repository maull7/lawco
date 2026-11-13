<?php

namespace App\Services;

use App\Models\DownloadedLegalDecision;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
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
    /** Kunci cache penanda portal MA sedang membatasi akses (HTTP 429); berlaku untuk daftar, PDF, dan refresh. */
    public const SOURCE_BLOCK_KEY = 'ma-source-blocked-until';

    /** Masa tunggu bila portal membalas 429 tanpa Retry-After: konservatif, tidak menebak portal sudah mengizinkan. */
    public const DEFAULT_BLOCK_SECONDS = 300;

    /** Daftar putusan dianggap segar selama ini; setelahnya tetap ditampilkan sambil dijadwalkan refresh. */
    public const LIST_FRESH_HOURS = 24;

    /** Daftar putusan disimpan selama ini sebagai cadangan saat portal/scraper/ngrok tidak tersedia. */
    public const LIST_KEEP_DAYS = 30;

    /** Portal MA terlambat mengirim PDF (scraper membalas 504): sementara, dicoba ulang lewat queue, bukan "Gagal diunduh". */
    public const PORTAL_TIMEOUT_ERROR = 'Portal MA lambat merespons (timeout). Unduhan dicoba ulang otomatis di latar belakang.';

    /** Jeda sebelum unduhan yang timeout dicoba ulang oleh DownloadLegalDecisionJob. */
    public const TIMEOUT_RETRY_SECONDS = 120;

    /**
     * Waktu sampai kapan portal MA dibatasi, atau null jika boleh diakses.
     */
    public function sourceBlockedUntil(): ?Carbon
    {
        $until = Cache::get(self::SOURCE_BLOCK_KEY);

        return $until && Carbon::parse($until)->isFuture() ? Carbon::parse($until) : null;
    }

    /**
     * Hentikan semua pengambilan baru dari portal MA sampai masa tunggu habis (tidak pernah memperpendek blokir yang ada).
     */
    public function blockSource(int $seconds): Carbon
    {
        $until = now()->addSeconds(max(1, $seconds));
        $current = $this->sourceBlockedUntil();
        if ($current && $current->greaterThan($until)) {
            return $current;
        }

        Cache::put(self::SOURCE_BLOCK_KEY, $until->toIso8601String(), $until);
        Log::channel('single')->warning('Portal MA membatasi akses (HTTP 429), pengambilan ditunda sampai '.$until->toDateTimeString());

        return $until;
    }

    /**
     * Lama tunggu dari respons 429 scraper: header Retry-After / field retry_after, atau default konservatif.
     */
    public function retryAfterSeconds(Response $response): int
    {
        $value = $response->header('Retry-After') ?: (string) ($response->json('retry_after') ?? '');

        return ctype_digit($value) ? max(1, (int) $value) : self::DEFAULT_BLOCK_SECONDS;
    }

    /**
     * Parameter yang menentukan isi satu halaman daftar, dinormalisasi agar filter A tidak terbaca sebagai filter B.
     *
     * @param  array{kategori?: string, peradilan?: string, periode?: string, tahun?: int|string|null, page?: int}  $params
     * @return array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}
     */
    public function livePageParams(array $params): array
    {
        return [
            'kategori' => trim((string) ($params['kategori'] ?? '')),
            'peradilan' => trim((string) ($params['peradilan'] ?? '')),
            'periode' => trim((string) ($params['periode'] ?? '')) ?: 'putus',
            'tahun' => (int) ($params['tahun'] ?? 0),
            'page' => max(1, (int) ($params['page'] ?? 1)),
        ];
    }

    /**
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     */
    public function livePageCacheKey(array $params): string
    {
        ksort($params);

        return 'ma_live_v2_'.md5(json_encode($params));
    }

    /**
     * Halaman daftar dari cache (segar atau cadangan), null jika belum pernah berhasil diambil.
     *
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     * @return array{data: array<string, mixed>, is_fresh: bool}|null
     */
    public function cachedLivePage(array $params): ?array
    {
        $data = Cache::get($this->livePageCacheKey($params));
        if (empty($data['items'])) {
            return null;
        }

        $fetchedAt = isset($data['cached_at']) ? Carbon::parse($data['cached_at']) : null;

        return ['data' => $data, 'is_fresh' => $fetchedAt !== null && $fetchedAt->greaterThan(now()->subHours(self::LIST_FRESH_HOURS))];
    }

    /**
     * Simpan halaman daftar hasil scraper beserta waktu pengambilannya.
     *
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     * @param  array<string, mixed>  $data
     */
    public function storeLivePage(array $params, array $data): void
    {
        // cached_at: waktu scraper mengambil dari portal (ditampilkan); pulled_at: waktu Laravel menarik dari scraper.
        $data['cached_at'] ??= now()->toIso8601String();
        $data['pulled_at'] = now()->toIso8601String();
        Cache::put($this->livePageCacheKey($params), $data, now()->addDays(self::LIST_KEEP_DAYS));
    }

    /**
     * Minta satu halaman daftar ke scraper. cacheOnly: dijawab dari cache scraper saja; forceRefresh: minta scraper mengambil ulang.
     *
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     *
     * @throws ConnectionException
     */
    public function requestLivePage(array $params, int $timeoutSeconds, bool $cacheOnly = false, bool $forceRefresh = false): Response
    {
        return $this->scraperRequest()
            ->connectTimeout(min(10, $timeoutSeconds))
            ->timeout($timeoutSeconds)
            ->get('/putusan', array_filter([
                'page' => $params['page'],
                'kategori' => $params['kategori'],
                'peradilan' => $params['peradilan'],
                'periode' => $params['periode'],
                'tahun' => $params['tahun'],
                'cache_only' => $cacheOnly ? 1 : null,
                'refresh' => $forceRefresh ? 1 : null,
            ]));
    }

    /**
     * Ambil metadata halaman detail putusan MA (judul + tabel Nomor, Hakim, Amar, dst.) lewat scraper.
     *
     * @return array{title: string, fields: list<array{label: string, value: string}>}|null
     */
    public function fetchDetail(string $url): ?array
    {
        if ($remembered = Cache::get($this->detailCacheKey($url))) {
            return $remembered;
        }

        if (! config('services.ma_scraper.url') || $this->sourceBlockedUntil()) {
            return null;
        }

        try {
            $response = $this->scraperRequest()->timeout(60)->get('/detail', ['url' => $url]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->status() === 429) {
            $this->blockSource($this->retryAfterSeconds($response));

            return null;
        }

        if (! $response->successful() || ! $response->json('success')) {
            return null;
        }

        return [
            'title' => (string) $response->json('title', ''),
            'fields' => array_values((array) $response->json('fields', [])),
        ];
    }

    /**
     * Simpan detail putusan yang ikut terkirim bersama PDF (header X-Detail: base64 JSON) agar preview tidak membuka portal lagi.
     */
    private function rememberDetail(string $url, string $encodedDetail): void
    {
        $detail = json_decode((string) base64_decode($encodedDetail, true), true);
        if (! is_array($detail) || empty($detail['fields'])) {
            return;
        }

        Cache::put($this->detailCacheKey($url), [
            'title' => (string) ($detail['title'] ?? ''),
            'fields' => array_values((array) $detail['fields']),
        ], now()->addDays(30));
    }

    private function detailCacheKey(string $url): string
    {
        return 'legal-decision-ma-detail:'.md5($url);
    }

    /**
     * Metadata integritas PDF yang disimpan bersama record unduhan.
     *
     * @return array{r2_path: ?string, sha256: string, pdf_validated: bool, download_error: null}
     */
    public function integrityMetadata(string $content, ?string $r2Path): array
    {
        return [
            'r2_path' => $r2Path,
            'sha256' => hash('sha256', $content),
            'pdf_validated' => str_starts_with(ltrim($content), '%PDF'),
            'download_error' => null,
        ];
    }

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

        if ($blockedUntil = $this->sourceBlockedUntil()) {
            return ['content' => null, 'error' => 'Portal MA sedang membatasi akses. Coba lagi setelah '.$blockedUntil->timezone('Asia/Jakarta')->format('H:i').' WIB.'];
        }

        try {
            // Tunnel ngrok/Cloudflare ke scraper kadang memutus koneksi sesaat: coba ulang sebelum menyerah.
            $response = $this->scraperRequest()
                ->timeout(130)
                ->retry(3, 1000, fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->get('/pdf', ['url' => $url]);
        } catch (ConnectionException) {
            return ['content' => null, 'error' => 'Koneksi ke layanan scraper terputus atau timeout (tunnel ngrok mati/lambat). Coba lagi beberapa saat lagi.'];
        }

        if ($response->status() === 429) {
            $until = $this->blockSource($this->retryAfterSeconds($response));

            return ['content' => null, 'error' => 'Portal MA sedang membatasi akses. Coba lagi setelah '.$until->timezone('Asia/Jakarta')->format('H:i').' WIB.'];
        }

        if ($response->status() === 504) {
            return ['content' => null, 'error' => self::PORTAL_TIMEOUT_ERROR];
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

        $this->rememberDetail($url, $response->header('X-Detail'));

        return ['content' => $body, 'error' => null];
    }

    /**
     * True jika kegagalan bersifat sementara (portal MA timeout) dan sebaiknya dicoba ulang, bukan dicatat gagal.
     */
    public function isTransientError(?string $error): bool
    {
        return $error === self::PORTAL_TIMEOUT_ERROR;
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
     * Hasil "ada" di-cache sehari, "tidak ada" 10 menit. uploadToR2/deleteFromR2 memperbarui cache ini langsung.
     */
    public function existsInR2(string $url): ?bool
    {
        if (! $this->isR2Configured()) {
            return null;
        }

        $objectPath = $this->objectPath($url);
        $cached = Cache::get($this->r2ExistsCacheKey($objectPath));
        if ($cached !== null) {
            return (bool) $cached;
        }

        try {
            $exists = Storage::disk('r2')->exists($objectPath);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('R2 exists check error: '.$e->getMessage());

            return null;
        }

        Cache::put($this->r2ExistsCacheKey($objectPath), $exists, $exists ? now()->addDay() : now()->addMinutes(10));

        return $exists;
    }

    private function r2ExistsCacheKey(string $objectPath): string
    {
        return 'legal-decision-r2-exists:'.md5($objectPath);
    }

    /**
     * Resolusi tahun putusan secara akurat:
     * 1. Ambil tahun dari tanggal putus / register / upload terlebih dahulu jika tersedia (format dd-mm-yyyy).
     * 2. Jika tidak ada tanggal, cari pola tahun (19xx / 20xx) dari judul putusan dan ambil kemunculan terakhir
     *    (karena nomor perkara bisa diawali angka mirip tahun, mis. "1999 B/PK/PJK/2026").
     * 3. Fallback ke input $rawYear jika valid, atau null.
     */
    public function resolveDecisionYear(?string $rawYear, ?string $title, ?string $tglPutus = null, ?string $tglRegister = null, ?string $tglUpload = null): ?int
    {
        foreach ([$tglPutus, $tglRegister, $tglUpload] as $dateStr) {
            $dateStr = trim((string) $dateStr);
            if ($dateStr !== '' && preg_match('/(?:^|[-\/ ])(20[0-2][0-9]|19[8-9][0-9])(?:$|[-\/ ])/', $dateStr, $matches)) {
                return (int) $matches[1];
            }
        }

        if ($title !== null && $title !== '') {
            if (preg_match_all('/\b(20[0-2][0-9]|19[8-9][0-9])\b/', $title, $matches) && ! empty($matches[1])) {
                return (int) end($matches[1]);
            }
        }

        $rawYear = trim((string) $rawYear);
        if ($rawYear !== '' && preg_match('/\b(20[0-2][0-9]|19[8-9][0-9])\b/', $rawYear, $matches)) {
            return (int) $matches[1];
        }

        return null;
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

        $tglRegister = $value('tgl_register');
        $tglPutus = $value('tgl_putus');
        $tglUpload = $value('tgl_upload');
        $resolvedYear = $this->resolveDecisionYear($value('year'), $title, $tglPutus, $tglRegister, $tglUpload);

        return DownloadedLegalDecision::create([
            'user_id' => $userId,
            'decision_number' => $title,
            'title' => $title,
            'court_name' => $value('court_name') ?? 'Mahkamah Agung',
            'classification' => $value('classification'),
            'year' => $resolvedYear ?: null,
            'tgl_register' => $tglRegister,
            'tgl_putus' => $tglPutus,
            'tgl_upload' => $tglUpload,
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
        Cache::forget($this->r2ExistsCacheKey($path));

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
                Cache::put($this->r2ExistsCacheKey($objectPath), true, now()->addDay());

                return "{$bucket}/{$objectPath}";
            }

            Log::channel('single')->warning("R2 upload error: gagal menulis {$objectPath}");
        } catch (\Throwable $e) {
            Log::channel('single')->warning('R2 upload error: '.$e->getMessage());
        }

        return null;
    }
}
