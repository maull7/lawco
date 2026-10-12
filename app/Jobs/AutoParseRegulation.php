<?php

namespace App\Jobs;

use App\Exceptions\ParsingCancelledException;
use App\Models\Regulation;
use App\Services\AutomaticRegulationParseService;
use App\Services\RegulationParserService;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class AutoParseRegulation implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 350;

    public int $tries = 0;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public int $expiresAt;

    public function __construct(public int $regulationId)
    {
        $this->onQueue(config('automatic-parse.queue'));
        $this->expiresAt = now()->addDays(7)->timestamp;
    }

    public function retryUntil(): DateTimeInterface
    {
        return Carbon::createFromTimestamp($this->expiresAt);
    }

    public function handle(RegulationParserService $parser): void
    {
        $batch = $this->batch();
        if (! $batch) {
            return;
        }
        if ($batch->cancelled()) {
            $current = Regulation::find($this->regulationId);
            if ($current?->parse_status === 'parsing' && ($current->parse_stats['auto_parse_batch_id'] ?? null) === $this->batchId) {
                $current->update(['parse_status' => 'not_parsed', 'parse_progress' => null]);
            }
            $this->record('cancelled', 'Batch dibatalkan.');

            return;
        }
        $regulation = Regulation::find($this->regulationId);
        if (! $regulation) {
            $this->record('skipped', 'Regulasi sudah dihapus.');

            return;
        }
        if ($regulation->parse_status === 'complete') {
            $this->record(($regulation->parse_stats['auto_parse_batch_id'] ?? null) === $this->batchId ? 'complete' : 'skipped', 'Regulasi sudah diparse.');

            return;
        }
        $owner = $regulation->parse_stats['auto_parse_batch_id'] ?? null;
        if ($regulation->parse_status === 'parsing' && $owner !== $this->batchId) {
            $this->record('skipped', 'Regulasi sedang diparse oleh proses lain.');

            return;
        }
        if ($regulation->parse_status !== 'parsing' && ! in_array($regulation->parse_status, [null, 'not_parsed'], true)) {
            $this->record($owner === $this->batchId ? $regulation->parse_status : 'skipped', $regulation->parse_error);

            return;
        }
        if (Cache::get('parse_cancel:regulation:'.$this->regulationId)) {
            if ($owner === $this->batchId) {
                $regulation->update(['parse_status' => 'not_parsed', 'parse_progress' => null]);
            }

            $this->record('cancelled', 'Parse dibatalkan oleh admin.');

            return;
        }

        try {
            if ($regulation->parse_status !== 'parsing') {
                $claimed = Regulation::query()->whereKey($regulation->id)
                    ->where(fn ($query) => $query->where('parse_status', 'not_parsed')->orWhereNull('parse_status'))
                    ->update(['parse_status' => 'parsing', 'parse_stats' => json_encode(['auto_parse_batch_id' => $this->batchId]), 'parse_progress' => 0, 'parse_error' => null]);
                if (! $claimed) {
                    $this->record('skipped', 'Regulasi telah diambil proses lain.');

                    return;
                }
                $this->record('parsing');
                $totalPages = $parser->getPageCount($regulation->file_path);
                if ($totalPages < 1) {
                    throw new RuntimeException('Gagal membaca jumlah halaman PDF.');
                }
                $method = $parser->detectPdfType($regulation->file_path) === 'text' ? 'text' : 'ocr';
                $regulation->update([
                    'parsed_at' => null, 'parsed_text' => null,
                    'parse_stats' => [
                        'auto_parse_batch_id' => $this->batchId,
                        'total_pages' => $totalPages, 'method' => $method, 'pdf_type' => $method,
                        'parsed_pages' => 0, 'empty_pages' => 0, 'char_total' => 0,
                        'ocr_pages' => 0, 'normal_pages' => 0, 'percent_parsed' => 0,
                        'processed_chunks' => [],
                    ],
                ]);
            }
            $regulation->refresh();
            $stats = $regulation->parse_stats;
            $totalPages = (int) $stats['total_pages'];
            $method = ($stats['pdf_type'] ?? 'text') === 'image' ? 'ocr' : 'text';
            $processed = $stats['processed_chunks'] ?? [];
            for ($start = 1; $start <= $totalPages; $start += ParseRegulationChunk::CHUNK_SIZE) {
                $end = min($totalPages, $start + ParseRegulationChunk::CHUNK_SIZE - 1);
                if (in_array($start.'-'.$end, $processed, true)) {
                    continue;
                }
                $chunk = new ParseRegulationChunk($regulation, $start, $end, $totalPages, $method);
                $chunk->onQueue(config('automatic-parse.queue'))->handle($parser);
                $fresh = $regulation->fresh();
                if ($fresh?->parse_status === 'parsing') {
                    $this->record('parsing');
                    $this->release();
                } elseif (in_array($fresh?->parse_status, ['complete', 'incomplete'], true)) {
                    $this->record($fresh->parse_status);
                } elseif ($fresh?->parsed_at !== null && $fresh?->parse_status === 'not_parsed') {
                    throw new RuntimeException('Tidak ada halaman yang berhasil dibaca.');
                } else {
                    $this->record('cancelled', 'Parse dibatalkan.');
                }

                return;
            }
        } catch (ParsingCancelledException) {
            $regulation->fresh()?->update(['parse_status' => 'not_parsed', 'parse_progress' => null]);
            $this->record('cancelled', 'Parse dibatalkan.');
        } catch (Throwable $exception) {
            $this->failed($exception);
            $this->fail($exception);
        }
    }

    private function record(string $status, ?string $error = null): void
    {
        app(AutomaticRegulationParseService::class)->recordDocumentResult($this->batchId, $this->regulationId, $status, $error);
    }

    public function failed(?Throwable $exception): void
    {
        $this->record('failed', $exception?->getMessage() ?? 'Proses parse otomatis gagal.');
        $regulation = Regulation::find($this->regulationId);
        if ($regulation && ($regulation->parse_stats['auto_parse_batch_id'] ?? null) === $this->batchId) {
            $regulation->update([
                'parse_status' => 'failed', 'parse_progress' => null,
                'parse_error' => mb_substr($exception?->getMessage() ?? 'Proses parse otomatis gagal.', 0, 500),
            ]);
        }
    }
}
