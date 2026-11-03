<?php

namespace App\Console\Commands;

use App\Models\DownloadedLegalDecision;
use App\Models\LegalDecisionDownloadFailure;
use App\Services\LegalDecisionPdfService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('legal-decisions:backfill-failures {--dry-run : Hanya tampilkan putusan gagal yang belum masuk arsip}')]
#[Description('Masukkan riwayat putusan MA yang gagal diunduh ke Putusan MA (download) dengan status "File gagal terunduh".')]
class BackfillFailedLegalDecisionDownloads extends Command
{
    public function handle(LegalDecisionPdfService $pdfService): int
    {
        $failures = LegalDecisionDownloadFailure::query()
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('downloaded_legal_decisions as d')
                ->whereColumn('d.source_url', 'legal_decision_download_failures.source_url'))
            ->orderBy('id')
            ->get();

        if ($failures->isEmpty()) {
            $this->info('Semua putusan yang gagal diunduh sudah ada di arsip.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Nomor Putusan', 'Error'], $failures->map(fn (LegalDecisionDownloadFailure $failure): array => [$failure->id, $failure->decision_number, $failure->error_message]));

            return self::SUCCESS;
        }

        $failures->each(fn (LegalDecisionDownloadFailure $failure): DownloadedLegalDecision => $pdfService->recordFailedDownload(
            $failure->source_url,
            ['decision_number' => $failure->decision_number],
            $failure->user_id,
            (string) ($failure->error_message ?: 'Pengunduhan file PDF gagal.'),
        ));

        $this->info("{$failures->count()} putusan gagal dimasukkan ke arsip Putusan MA (download).");

        return self::SUCCESS;
    }
}
