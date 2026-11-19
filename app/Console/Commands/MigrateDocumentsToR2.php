<?php

namespace App\Console\Commands;

use App\Helpers\DocumentStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('documents:migrate-to-r2
    {--check : Only test the R2 connection (write, read, delete)}
    {--stats : Report file counts/sizes (local vs R2) and whether DB-referenced files exist on R2}
    {--prune : Delete local files that already exist on R2 with the same size (requires PDF_DISK set to the R2 document disk)}
    {--dry-run : With --prune, only list what would be deleted}')]
#[Description('Copy uploaded documents from the local public disk to Cloudflare R2 (keeps local files; --prune removes them once on R2).')]
class MigrateDocumentsToR2 extends Command
{
    public const DIRS = ['regulations', 'regulation-documents', 'category-files', 'legal-cases', 'review-documents', 'payment-proofs'];

    /** @var array<string, string> table => column holding a path on the document disk */
    private const DB_COLUMNS = [
        'regulations' => 'file_path',
        'regulation_documents' => 'file_path',
        'category_files' => 'file_path',
        'legal_cases' => 'file_path',
        'review_documents' => 'file_path',
        'user_packages' => 'payment_proof',
    ];

    public function handle(): int
    {
        $this->line('['.now()->format('Y-m-d H:i:s').'] start');

        $r2 = Storage::disk(DocumentStorage::r2DiskName());
        $local = Storage::disk('public');

        if ($this->option('stats')) {
            $this->stats($local, $r2);

            return self::SUCCESS;
        }

        if ($this->option('prune')) {
            return $this->prune($local, $r2, (bool) $this->option('dry-run'));
        }

        $probe = '_healthcheck/'.uniqid().'.txt';
        $r2->put($probe, 'ok');
        $ok = $r2->get($probe) === 'ok';
        $r2->delete($probe);
        if (! $ok) {
            $this->error('R2 read-back failed.');

            return self::FAILURE;
        }
        $this->info('R2 connection OK.');
        if ($this->option('check')) {
            return self::SUCCESS;
        }

        $limitBytes = (int) round((float) config('filesystems.r2_daily_copy_limit_gb', 5) * 1024 ** 3);
        $quotaKey = 'r2_copy_bytes:'.today()->toDateString();
        $copiedToday = (int) Cache::get($quotaKey, 0);
        $this->info("Kuota harian: {$this->mb($copiedToday)} / {$this->mb($limitBytes)} terpakai hari ini.");

        $copied = $skipped = $failed = 0;
        $quotaReached = false;
        foreach (self::DIRS as $dir) {
            foreach ($local->allFiles($dir) as $path) {
                try {
                    $size = $local->size($path);
                    if ($r2->exists($path) && $r2->size($path) === $size) {
                        $skipped++;

                        continue;
                    }
                    // A single file bigger than the whole quota may still go first thing in the day, or it would never migrate.
                    if ($copiedToday > 0 && $copiedToday + $size > $limitBytes) {
                        $quotaReached = true;

                        break 2;
                    }
                    $r2->writeStream($path, $local->readStream($path));
                    $copiedToday += $size;
                    Cache::put($quotaKey, $copiedToday, now()->addDays(2));
                    $copied++;
                    $this->line("copied {$path} ({$this->mb($size)})");
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("FAILED {$path}: {$e->getMessage()}");
                }
            }
        }

        if ($quotaReached) {
            $this->warn("Kuota harian {$this->mb($limitBytes)} tercapai. Sisa file dilanjutkan besok (jalankan Migrasi lagi).");
        }
        $this->info("Done. copied={$copied} skipped={$skipped} failed={$failed} copied_today={$this->mb($copiedToday)}.");
        $this->info('['.now()->format('Y-m-d H:i:s').'] finished');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Delete local copies only when R2 has the same file at the same size. */
    private function prune(FilesystemAdapter $local, FilesystemAdapter $r2, bool $dryRun): int
    {
        if (! $dryRun && ! DocumentStorage::usesR2()) {
            $this->error('Ditolak: PDF_DISK masih "'.DocumentStorage::diskName().'". Set PDF_DISK='.DocumentStorage::r2DiskName().' dulu, file lokal masih dipakai.');

            return self::FAILURE;
        }

        $this->info($dryRun ? '== PREVIEW hapus (tidak ada file yang dihapus)' : '== HAPUS file lokal yang sudah ada di R2');
        $deleted = $kept = $failed = $bytes = 0;
        foreach (self::DIRS as $dir) {
            foreach ($local->allFiles($dir) as $path) {
                try {
                    $size = $local->size($path);
                    if (! $r2->exists($path) || $r2->size($path) !== $size) {
                        $kept++;
                        $this->warn("keep {$path} (belum ada / beda ukuran di R2)");

                        continue;
                    }
                    if (! $dryRun) {
                        $local->delete($path);
                    }
                    $deleted++;
                    $bytes += $size;
                    $this->line(($dryRun ? 'would delete ' : 'deleted ').$path);
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("FAILED {$path}: {$e->getMessage()}");
                }
            }
        }

        $this->info(($dryRun ? 'Preview: would_delete' : 'Done: deleted')."={$deleted} ({$this->mb($bytes)}) kept={$kept} failed={$failed}.");
        $this->info('['.now()->format('Y-m-d H:i:s').'] finished');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function stats(FilesystemAdapter $local, FilesystemAdapter $r2): void
    {
        $this->info('== Files per folder (local vs R2)');
        $totalLocal = $totalR2 = 0;
        foreach (self::DIRS as $dir) {
            $localFiles = $local->allFiles($dir);
            $localSize = array_sum(array_map(fn ($p) => $local->size($p), $localFiles));
            $r2Files = $r2->allFiles($dir);
            $r2Size = array_sum(array_map(fn ($p) => $r2->size($p), $r2Files));
            $totalLocal += $localSize;
            $totalR2 += $r2Size;
            $this->line(sprintf('%-22s local=%5d files %10s | r2=%5d files %10s', $dir, count($localFiles), $this->mb($localSize), count($r2Files), $this->mb($r2Size)));
        }
        $this->line(sprintf('%-22s local=%s | r2=%s', 'TOTAL', $this->mb($totalLocal), $this->mb($totalR2)));

        $this->info('== Database references');
        foreach (self::DB_COLUMNS as $table => $column) {
            $paths = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column);
            if ($paths->isEmpty()) {
                continue;
            }
            $inR2 = $paths->filter(fn ($p) => $r2->exists($p))->count();
            $inLocal = $paths->filter(fn ($p) => $local->exists($p))->count();
            $this->line("{$table}.{$column}: total={$paths->count()} on_r2={$inR2} on_local={$inLocal}"
                .($inR2 < $paths->count() ? '  <-- '.($paths->count() - $inR2).' missing on R2' : '  OK'));
        }
        $this->info('['.now()->format('Y-m-d H:i:s').'] finished');
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
