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

    /**
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     */
    public function __construct(public array $params) {}

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
            $response = $pdfService->scraperRequest()->connectTimeout(10)->timeout(110)->get('/putusan', array_filter([
                'page' => $this->params['page'],
                'kategori' => $this->params['kategori'],
                'peradilan' => $this->params['peradilan'],
                'periode' => $this->params['periode'],
                'tahun' => $this->params['tahun'],
            ]));
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
}
