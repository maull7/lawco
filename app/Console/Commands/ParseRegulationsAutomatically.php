<?php

namespace App\Console\Commands;

use App\Services\AutomaticRegulationParseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Signature('regulations:parse-automatic {--dry-run : Show eligible documents without dispatching jobs}')]
#[Description('Start the daily batch of at most 3000 unparsed regulation PDFs.')]
class ParseRegulationsAutomatically extends Command
{
    public function handle(AutomaticRegulationParseService $service): int
    {
        $sectorIds = $service->scheduledSectorIds();
        $sectors = $service->scheduledSectors();
        if ($sectorIds === [] || $sectors->count() !== count($sectorIds)) {
            $this->note('Parse otomatis dilewati: pilih sektor aktif di menu Pengaturan Parse Otomatis.');

            return self::SUCCESS;
        }
        $limit = (int) config('automatic-parse.daily_limit');
        if ($this->option('dry-run')) {
            $this->info(count($service->candidateIds($limit, sectorIds: $sectorIds)).' regulasi memenuhi syarat; tidak ada job dikirim.');

            return self::SUCCESS;
        }
        try {
            $batch = $service->start($limit, 'scheduled', sectorIds: $sectorIds);
            $this->note($batch ? $batch->options['run_total'].' regulasi dijadwalkan dalam '.$batch->options['batch_count'].' batch parse otomatis.' : 'Tidak ada batch baru yang perlu dimulai.');
        } catch (ValidationException|LockTimeoutException) {
            $this->note('Parse otomatis dilewati karena batch atau permintaan sebelumnya masih berjalan.');
        } catch (Throwable $exception) {
            $this->note('Parse otomatis gagal dimulai: '.mb_substr($exception->getMessage(), 0, 300));

            throw $exception;
        }

        return self::SUCCESS;
    }

    /**
     * Simpan hasil jadwal harian agar kartu ringkasan bisa menjelaskan kenapa tidak ada batch.
     */
    private function note(string $message): void
    {
        $this->info($message);
        Cache::put('automatic-run-note:parse:'.now(config('automatic-parse.timezone'))->format('Y-m-d'), [
            'message' => $message,
            'at' => now(config('automatic-parse.timezone'))->format('H:i'),
        ], now()->addDays(90));
    }
}
