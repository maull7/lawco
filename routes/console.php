<?php

use App\Jobs\SyncJdihRegulations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncJdihRegulations('jdih_komdigi'), 'jdih', 'redis')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->environments(['production']);

Schedule::job(new SyncJdihRegulations('jdih_kemenhub'), 'jdih', 'redis')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->environments(['production']);
