<?php

namespace App\Http\Controllers;

use App\Jobs\SyncJdihRegulations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ScrapingFailureController extends Controller
{
    public function index(): View
    {
        $failures = DB::table('failed_jobs')
            ->where('queue', 'jdih')
            ->where('payload', 'like', '%SyncJdihRegulations%')
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate(20, ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        $failures->getCollection()->transform(function (object $failure): object {
            $payload = json_decode($failure->payload, true);
            $failure->job_name = is_array($payload) && is_string($payload['displayName'] ?? null)
                ? $payload['displayName'] : 'Sinkronisasi JDIH';
            $failure->error_message = strtok($failure->exception, "\r\n") ?: 'Pesan error tidak tersedia.';

            return $failure;
        });

        return view('scraping-failures.index', compact('failures'));
    }

    public function retry(string $uuid): RedirectResponse
    {
        try {
            $retried = DB::transaction(function () use ($uuid): bool {
                $failure = DB::table('failed_jobs')->where('uuid', $uuid)
                    ->where('queue', 'jdih')->lockForUpdate()->first();
                $payload = $failure ? json_decode($failure->payload, true) : null;

                if (! is_array($payload) || ($payload['data']['commandName'] ?? null) !== SyncJdihRegulations::class
                    || ! is_string($payload['data']['command'] ?? null)) {
                    return false;
                }

                if (Artisan::call('queue:retry', ['id' => [$uuid], '--no-interaction' => true]) !== 0) {
                    throw new RuntimeException('Retry antrean JDIH gagal.');
                }

                return true;
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('scraping-failures.index')
                ->with('error', 'Gagal memasukkan proses ke antrean. Silakan coba lagi; riwayat gagal tetap tersimpan.');
        }

        return redirect()->route('scraping-failures.index')->with(
            $retried ? 'success' : 'error',
            $retried ? 'Retry sinkronisasi berhasil dimasukkan ke antrean. Catatan gagal lama dihapus; bila gagal lagi akan muncul catatan baru.'
                : 'Riwayat tidak tersedia atau data proses tidak valid untuk retry.',
        );
    }
}
