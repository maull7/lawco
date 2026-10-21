<?php

namespace App\Console\Commands;

use App\Jobs\CopyRegulationParsedTextChunk;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('regulations:copy-parsed-text
    {--chunk=50 : Jumlah regulasi per job}
    {--check : Hanya cek berapa regulasi yang belum tersalin}')]
#[Description('Kirim job ke queue untuk menyalin regulations.parsed_text ke regulation_texts. Aman dijalankan ulang; kolom lama tidak diubah.')]
class CopyRegulationParsedText extends Command
{
    public function handle(): int
    {
        $source = DB::table('regulations')->whereNotNull('parsed_text');

        if ($this->option('check')) {
            return $this->reportMissing($source);
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $jobCount = 0;

        (clone $source)->select('id')->chunkById(1000, function ($regulations) use ($chunkSize, &$jobCount) {
            foreach ($regulations->pluck('id')->chunk($chunkSize) as $regulationIds) {
                CopyRegulationParsedTextChunk::dispatch($regulationIds->values()->all());
                $jobCount++;
            }
        });

        $this->info("{$jobCount} job dikirim ke queue 'default'. Cek hasilnya: php artisan regulations:copy-parsed-text --check");

        return self::SUCCESS;
    }

    private function reportMissing(Builder $source): int
    {
        $missingCount = (clone $source)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('regulation_texts')
                ->whereColumn('regulation_texts.regulation_id', 'regulations.id'))
            ->count();

        if ($missingCount > 0) {
            $this->error("{$missingCount} regulasi belum tersalin.");

            return self::FAILURE;
        }

        $this->info('Semua parsed_text sudah tersalin ke regulation_texts.');

        return self::SUCCESS;
    }
}
