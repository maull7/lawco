<?php

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $sources = JdihTarget::query()
        ->where('is_active', true)
        ->orderBy('id')
        ->pluck('source');

    if ($sources->isEmpty()) {
        Log::channel('single')->warning('JDIH sync terjadwal dilewati karena belum ada target aktif.');

        return;
    }

    foreach ($sources as $source) {
        SyncJdihRegulations::dispatch($source, 0, null, true);
    }
    Log::channel('single')->info('Sinkronisasi JDIH pukul 01.00 WIB dijadwalkan dari sisa PDF di folder scraper.', [
        'sources' => $sources->all(),
        'from_folder' => true,
    ]);
})
    ->name('jdih-sync-active-targets')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->environments(['production']);

Schedule::command('regulations:parse-automatic')
    ->name('regulations-nightly-automatic-parse')
    ->dailyAt('20:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->environments(['production']);

Schedule::command('regulations:extract-references-automatic')
    ->name('regulations-nightly-automatic-extract')
    ->dailyAt(config('automatic-parse.extract_time'))
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->environments(['production']);
