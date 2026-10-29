<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ma:sync-putusan {--pages=10 : Jumlah halaman yang disinkronkan} {--api-key= : API key proxy unblocker}')]
#[Description('Jalankan background sinkronisasi data Putusan Mahkamah Agung via Python')]
class SyncMahkamahAgungPutusan extends Command
{
    public function handle(): int
    {
        $pages = (int) $this->option('pages');
        $apiKey = $this->option('api-key');

        $this->info("Menjalankan sinkronisasi Putusan MA ({$pages} halaman)...");

        $scriptPath = base_path('scripts/ma_scraper.py');
        $python = 'python';

        $cmd = escapeshellcmd("{$python} {$scriptPath} --pages={$pages}");
        if ($apiKey) {
            $cmd .= ' --api-key='.escapeshellarg($apiKey);
        }

        passthru($cmd, $returnVar);

        if ($returnVar === 0) {
            $this->info('Sinkronisasi selesai.');

            return self::SUCCESS;
        }

        $this->error('Sinkronisasi mengalami kendala.');

        return self::FAILURE;
    }
}
