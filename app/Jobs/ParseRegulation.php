<?php

namespace App\Jobs;

use App\Exceptions\ParsingCancelledException;
use App\Models\Regulation;
use App\Services\RegulationParserService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ParseRegulation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $queue = 'parsing';

    public $timeout = 300;

    public $tries = 3;

    public $backoff = [30, 60];

    public $uniqueFor = 3600;

    public function __construct(
        public Regulation $regulation,
    ) {}

    private function cancelKey(): string
    {
        return "parse_cancel:regulation:{$this->regulation->id}";
    }

    public function handle(RegulationParserService $parser): void
    {
        $regulation = $this->regulation->fresh();

        if (! $regulation) {
            return;
        }

        if ($regulation->parse_status === 'complete') {
            return;
        }

        if ($regulation->parse_status !== 'parsing') {
            $regulation->update(['parse_status' => 'parsing', 'parse_progress' => 0, 'parse_error' => null]);
        }

        try {
            $this->checkCancelled();
            $totalPages = $parser->getPageCount($regulation->file_path);

            if ($totalPages < 1) {
                throw new \RuntimeException('Gagal membaca jumlah halaman PDF.');
            }

            $method = $parser->detectPdfType($regulation->file_path) === 'text' ? 'text' : 'ocr';
            $regulation->update([
                'parsed_at' => null,
                'parsed_text' => null,
                'parse_status' => 'parsing',
                'parse_progress' => 0,
                'parse_error' => null,
                'parse_stats' => [
                    'pdf_type' => $method,
                    'total_pages' => $totalPages,
                    'parsed_pages' => 0,
                    'empty_pages' => 0,
                    'percent_parsed' => 0,
                    'normal_pages' => 0,
                    'ocr_pages' => 0,
                    'char_total' => 0,
                    'used_ocr' => $method === 'ocr',
                    'method' => $method,
                    'chunk_size' => ParseRegulationChunk::CHUNK_SIZE,
                    'processed_chunks' => [],
                ],
            ]);

            $jobs = [];
            for ($startPage = 1; $startPage <= $totalPages; $startPage += ParseRegulationChunk::CHUNK_SIZE) {
                $jobs[] = new ParseRegulationChunk(
                    $regulation,
                    $startPage,
                    min($startPage + ParseRegulationChunk::CHUNK_SIZE - 1, $totalPages),
                    $totalPages,
                    $method,
                );
            }

            Bus::chain($jobs)->dispatch();
        } catch (ParsingCancelledException $e) {
            Log::info("ParseRegulation cancelled for regulation {$regulation->id}");
            $regulation->fresh()?->update(['parse_status' => 'not_parsed', 'parse_progress' => null, 'parse_error' => null]);
            Cache::forget($this->cancelKey());

            return;
        } catch (\Throwable $e) {
            Log::error("ParseRegulation exception for regulation {$regulation->id}: {$e->getMessage()}");
            $regulation->fresh()?->update(['parse_status' => 'failed', 'parse_progress' => null, 'parse_error' => $this->truncateError($e->getMessage())]);

            throw $e;
        }

    }

    public function uniqueId(): string
    {
        return "parse-regulation:{$this->regulation->id}";
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ParseRegulation job failed for regulation {$this->regulation->id}: {$e->getMessage()}");
        $this->regulation->fresh()?->update([
            'parse_status' => 'failed',
            'parse_progress' => null,
            'parse_error' => $this->truncateError($this->friendlyErrorMessage($e)),
        ]);
    }

    private function friendlyErrorMessage(\Throwable $e): string
    {
        if ($e instanceof MaxAttemptsExceededException
            || preg_match('/has been attempted too many times|released a job that has been attempted|has timed out/i', $e->getMessage())) {
            return 'Proses parse gagal di latar belakang. Silakan coba parse ulang.';
        }

        return $e->getMessage();
    }

    private function checkCancelled(): void
    {
        if (Cache::get($this->cancelKey())) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }
    }

    private function truncateError(string $message): string
    {
        return mb_substr($message, 0, 500);
    }
}
