<?php

namespace App\Console\Commands;

use App\Services\AutomaticRegulationParseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;

#[Signature('regulations:parse-automatic {--dry-run : Show eligible documents without dispatching jobs}')]
#[Description('Start the daily batch of at most 500 unparsed regulation PDFs.')]
class ParseRegulationsAutomatically extends Command
{
    public function handle(AutomaticRegulationParseService $service): int
    {
        $limit = (int) config('automatic-parse.daily_limit');
        if ($this->option('dry-run')) {
            $this->info(count($service->candidateIds($limit)).' regulasi memenuhi syarat; tidak ada job dikirim.');

            return self::SUCCESS;
        }
        try {
            $batch = $service->start($limit, 'scheduled');
            $this->info($batch ? $batch->totalJobs.' regulasi dijadwalkan untuk parse otomatis.' : 'Tidak ada batch baru yang perlu dimulai.');
        } catch (ValidationException|LockTimeoutException) {
            $this->info('Parse otomatis dilewati karena batch atau permintaan sebelumnya masih berjalan.');
        }

        return self::SUCCESS;
    }
}
