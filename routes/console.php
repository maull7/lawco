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
        SyncJdihRegulations::dispatch($source);
    }
})
    ->name('jdih-sync-active-targets')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->environments(['production']);
