<?php

namespace App\Jobs;

use App\Services\LegalDecisionPdfService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ambil satu halaman daftar Putusan MA dari scraper di belakang layar lalu simpan ke cache.
 *
 * Halaman web tidak pernah menunggu portal MA: ia menampilkan cache dan menjadwalkan job ini.
 * ShouldBeUnique (atomic lock per kombinasi filter) membuat banyak user yang membuka filter sama
 * hanya menghasilkan satu job. Selama portal membatasi (429), job ditunda sampai masa tunggu habis.
 */
class RefreshLegalDecisionPage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Portal MA bisa butuh sampai 90 detik per halaman. */
    public int $timeout = 120;

    public int $uniqueFor = 900;

    /** Jumlah halaman yang disiapkan saat user membuka halaman N: N sampai N + PAGES_PER_OPEN - 1. */
    public const PAGES_PER_OPEN = 5;

    /** Batas halaman portal MA. */
    public const PORTAL_MAX_PAGE = 499;

    /** Jeda antar halaman berikutnya agar portal MA tidak menerima banyak request sekaligus. */
    public const NEXT_PAGE_DELAY_SECONDS = 5;

    /**
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     */
    public function __construct(public array $params)
    {
        $this->onQueue('ma-refresh');
    }

    public function uniqueId(): string
    {
        return app(LegalDecisionPdfService::class)->livePageCacheKey($this->params);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * Kunci cache pesan kegagalan terakhir untuk halaman ini (ditampilkan saat belum ada cache sama sekali).
     *
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     */
    public static function errorKey(array $params): string
    {
        return app(LegalDecisionPdfService::class)->livePageCacheKey($params).'_error';
    }

    public function handle(LegalDecisionPdfService $pdfService): void
    {
        if ($blockedUntil = $pdfService->sourceBlockedUntil()) {
            $this->release(max(5, (int) ceil(now()->diffInSeconds($blockedUntil))));

            return;
        }

        try {
            $response = $pdfService->requestLivePage($this->params, 110);
        } catch (ConnectionException $e) {
            Log::channel('single')->error('Refresh Putusan MA: scraper tidak dapat dihubungi: '.$e->getMessage());
            Cache::put(self::errorKey($this->params), 'Scraper/ngrok tidak dapat dihubungi. Data akan diambil otomatis saat scraper kembali aktif.', 600);
            $this->release(60);

            return;
        }

        if ($response->status() === 429) {
            $seconds = $pdfService->retryAfterSeconds($response);
            $pdfService->blockSource($seconds);
            $this->release($seconds);

            return;
        }

        $json = $response->json();
        if (! empty($json['success']) && ! empty($json['items'])) {
            $pdfService->storeLivePage($this->params, $json);
            Cache::forget(self::errorKey($this->params));

            return;
        }

        $error = $json['error'] ?? ('Respons scraper tidak valid (HTTP '.$response->status().').');
        Log::channel('single')->error('Refresh Putusan MA gagal: HTTP '.$response->status().' '.substr($response->body(), 0, 400));
        Cache::put(self::errorKey($this->params), $error, 600);

        // Halaman memang kosong untuk filter ini (sukses tanpa item): jangan diulang.
        if (empty($json['success'])) {
            $this->release(120);
        }
    }

    /**
     * Jadwalkan halaman yang dibuka user (langsung) dan halaman-halaman berikutnya (berjeda) yang belum segar di cache.
     * Contoh: buka halaman 420 -> job 420, 421, 422, 423, 424. Job unik per halaman, jadi buka ulang tidak membuat dobel.
     *
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     * @param  bool  $forceCurrent  Klik "Perbarui": halaman yang dibuka tetap dijadwalkan walau cache masih segar.
     * @return bool Apakah halaman yang dibuka ikut dijadwalkan.
     */
    public static function dispatchForOpenedPage(array $params, int $maxPage = self::PORTAL_MAX_PAGE, bool $forceCurrent = false): bool
    {
        $pdfService = app(LegalDecisionPdfService::class);
        $lastPage = min(max($maxPage, $params['page']), self::PORTAL_MAX_PAGE, $params['page'] + self::PAGES_PER_OPEN - 1);
        $isCurrentQueued = false;
        $delay = 0;

        for ($page = $params['page']; $page <= $lastPage; $page++) {
            $pageParams = [...$params, 'page' => $page];
            $isCurrent = $page === $params['page'];

            if (! ($isCurrent && $forceCurrent) && ($pdfService->cachedLivePage($pageParams)['is_fresh'] ?? false)) {
                continue;
            }

            $pending = self::dispatch($pageParams);
            if ($delay > 0) {
                $pending->delay(now()->addSeconds($delay));
            }
            unset($pending); // dispatch terjadi saat PendingDispatch dilepas

            $isCurrentQueued = $isCurrentQueued || $isCurrent;
            $delay += self::NEXT_PAGE_DELAY_SECONDS;
        }

        return $isCurrentQueued;
    }
}
