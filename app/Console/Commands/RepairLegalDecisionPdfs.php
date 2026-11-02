<?php

namespace App\Console\Commands;

use App\Models\DownloadedLegalDecision;
use App\Services\LegalDecisionPdfService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('legal-decisions:repair-pdfs {--dry-run : Hanya tampilkan putusan yang belum punya PDF di R2}')]
#[Description('Lengkapi PDF putusan MA yang sudah tercatat di arsip tetapi belum tersimpan di Cloudflare R2.')]
class RepairLegalDecisionPdfs extends Command
{
    public function handle(LegalDecisionPdfService $pdfService): int
    {
        if (! $pdfService->isR2Configured()) {
            $this->error('Cloudflare R2 belum dikonfigurasi (R2 key/endpoint/bucket di .env).');

            return self::FAILURE;
        }

        $missing = DownloadedLegalDecision::query()
            ->whereNotNull('source_url')
            ->orderBy('id')
            ->get()
            ->filter(fn (DownloadedLegalDecision $decision): bool => empty($decision->metadata['r2_path']));

        if ($missing->isEmpty()) {
            $this->info('Semua putusan di arsip sudah memiliki PDF di R2.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Nomor Putusan'], $missing->map(fn (DownloadedLegalDecision $decision): array => [$decision->id, $decision->decision_number]));

            return self::SUCCESS;
        }

        $rows = [];
        $failedCount = 0;

        foreach ($missing as $decision) {
            ['content' => $content, 'r2_path' => $r2Path, 'error' => $error] = $pdfService->archive($decision->source_url);

            if ($error !== null) {
                $failedCount++;
                $rows[] = [$decision->id, $decision->decision_number, 'GAGAL', $error];

                continue;
            }

            $decision->update([
                'file_size' => strlen($content),
                'metadata' => [...($decision->metadata ?? []), 'r2_path' => $r2Path],
            ]);
            $rows[] = [$decision->id, $decision->decision_number, 'OK', $r2Path];
        }

        $this->table(['ID', 'Nomor Putusan', 'Status', 'Keterangan'], $rows);
        $this->line(($missing->count() - $failedCount)." berhasil dilengkapi, {$failedCount} gagal.");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
