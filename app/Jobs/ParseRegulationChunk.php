<?php

namespace App\Jobs;

use App\Exceptions\ParsingCancelledException;
use App\Models\Regulation;
use App\Services\RegulationParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ParseRegulationChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public const CHUNK_SIZE = 5;

    public $queue = 'parsing';

    public $timeout = 300;

    public $tries = 2;

    public $backoff = [10, 30];

    public function __construct(
        public Regulation $regulation,
        public int $startPage,
        public int $endPage,
        public int $totalPages,
        public string $method,
    ) {}

    public function handle(RegulationParserService $parser): void
    {
        $regulation = $this->regulation->fresh();

        if (! $regulation || $regulation->parse_status !== 'parsing') {
            return;
        }

        $chunkKey = "{$this->startPage}-{$this->endPage}";
        $stats = $regulation->parse_stats ?? [];

        if (in_array($chunkKey, $stats['processed_chunks'] ?? [], true)) {
            return;
        }

        try {
            $this->checkCancelled();
            $result = $parser->parseRegulationChunk(
                $regulation,
                $this->startPage,
                $this->endPage,
                $this->method,
                fn () => $this->checkCancelled(),
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }

            $this->storeChunk($regulation, $result, $chunkKey);
        } catch (ParsingCancelledException $e) {
            Log::info("ParseRegulationChunk cancelled for regulation {$regulation->id}");
            $regulation->update([
                'parse_status' => 'not_parsed',
                'parse_progress' => null,
                'parse_error' => null,
            ]);
            Cache::forget($this->cancelKey());
        } catch (\Throwable $e) {
            Log::error("ParseRegulationChunk exception for regulation {$regulation->id}, pages {$chunkKey}: {$e->getMessage()}");
            $regulation->update([
                'parse_status' => 'failed',
                'parse_progress' => null,
                'parse_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->regulation->fresh()?->update([
            'parse_status' => 'failed',
            'parse_progress' => null,
            'parse_error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }

    private function storeChunk(Regulation $regulation, array $result, string $chunkKey): void
    {
        $regulation = $regulation->fresh();
        $stats = $regulation->parse_stats ?? [];
        $processedChunks = $stats['processed_chunks'] ?? [];

        if (in_array($chunkKey, $processedChunks, true)) {
            return;
        }

        $pages = $result['stats']['pages'];
        $parsedPages = ($stats['parsed_pages'] ?? 0) + count(array_filter($pages, fn (array $page): bool => $page['char_count'] > 0));
        $emptyPages = ($stats['empty_pages'] ?? 0) + count(array_filter($pages, fn (array $page): bool => $page['char_count'] === 0));
        $charTotal = ($stats['char_total'] ?? 0) + array_sum(array_column($pages, 'char_count'));
        $processedChunks[] = $chunkKey;
        $percentParsed = round(($parsedPages / $this->totalPages) * 100);
        $text = trim($result['text'] ?? '');
        $existingText = trim($regulation->parsed_text ?? '');
        $combinedText = $existingText === '' || $text === '' ? $existingText.$text : $existingText."\n\n".$text;
        $isLastChunk = $this->endPage >= $this->totalPages;

        $stats = array_merge($stats, [
            'parsed_pages' => $parsedPages,
            'empty_pages' => $emptyPages,
            'percent_parsed' => $percentParsed,
            'normal_pages' => $this->method === 'text' ? $parsedPages : 0,
            'ocr_pages' => $this->method === 'ocr' ? $parsedPages : 0,
            'char_total' => $charTotal,
            'processed_chunks' => $processedChunks,
        ]);

        $regulation->update([
            'parsed_text' => $combinedText,
            'parse_stats' => $stats,
            'parse_progress' => $isLastChunk ? 100 : min(99, round(($this->endPage / $this->totalPages) * 100)),
            'parse_status' => $isLastChunk ? ($percentParsed >= 95 ? 'complete' : ($percentParsed > 0 ? 'incomplete' : 'not_parsed')) : 'parsing',
            'parsed_at' => $isLastChunk ? now() : null,
        ]);
    }

    private function cancelKey(): string
    {
        return "parse_cancel:regulation:{$this->regulation->id}";
    }

    private function checkCancelled(): void
    {
        if (Cache::get($this->cancelKey())) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }
    }
}
