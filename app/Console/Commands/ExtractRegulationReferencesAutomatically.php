<?php

namespace App\Console\Commands;

use App\Services\AutomaticReferenceExtractService;
use App\Services\AutomaticRegulationParseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;

#[Signature('regulations:extract-references-automatic {--dry-run : Show eligible documents without dispatching jobs}')]
#[Description('Start the daily batch of at most 1000 parsed regulations to extract related regulations from.')]
class ExtractRegulationReferencesAutomatically extends Command
{
    public function handle(AutomaticRegulationParseService $parseService, AutomaticReferenceExtractService $service): int
    {
        $sectorIds = $parseService->scheduledSectorIds();
        if ($sectorIds === [] || $parseService->scheduledSectors()->count() !== count($sectorIds)) {
            $this->info('Ekstrak otomatis dilewati: pilih sektor aktif di menu Pengaturan Parse Otomatis.');

            return self::SUCCESS;
        }
        $limit = (int) config('automatic-parse.extract_daily_limit');
        if ($this->option('dry-run')) {
            $this->info(count($service->candidateIds($limit, sectorIds: $sectorIds)).' regulasi memenuhi syarat; tidak ada job dikirim.');

            return self::SUCCESS;
        }
        try {
            $batch = $service->start($limit, 'scheduled', sectorIds: $sectorIds);
            $this->info($batch ? $batch->options['run_total'].' regulasi dijadwalkan dalam '.$batch->options['batch_count'].' batch ekstrak otomatis.' : 'Tidak ada batch baru yang perlu dimulai.');
        } catch (ValidationException|LockTimeoutException) {
            $this->info('Ekstrak otomatis dilewati karena batch atau permintaan sebelumnya masih berjalan.');
        }

        return self::SUCCESS;
    }
}
