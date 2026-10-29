<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMahkamahAgungJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $pages = 3) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lock = cache()->lock('ma_sync_running', 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $scriptPath = base_path('scripts/ma_scraper.py');
            $cmd = escapeshellcmd("python {$scriptPath} --pages={$this->pages}");
            shell_exec($cmd);
        } finally {
            $lock->release();
        }
    }
}
