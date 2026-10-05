<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class SyncJdihRegulations implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1800;

    public function __construct(
        public ?string $source = null,
        public int $limit = 0,
    ) {
        $this->onQueue('jdih');
    }

    public function uniqueId(): string
    {
        return sprintf('jdih-regulations-sync:%s:%d', $this->source ?? 'all', $this->limit);
    }

    public function displayName(): string
    {
        return sprintf('JDIH sync (%s)', $this->source ?? 'all');
    }

    public function handle(): void
    {
        $output = new BufferedOutput;
        $parameters = ['--limit' => $this->limit];

        if ($this->source !== null) {
            $parameters['--source'] = $this->source;
        }

        $exitCode = Artisan::call('jdih:sync', $parameters, $output);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output->fetch()))));
        $summary = array_values(array_filter(
            $lines,
            static fn (string $line): bool => preg_match('/^(Total source|Available PDF|Missing PDF|Imported|Already synced|Failed|Needs review|Pending)\s*:/', $line) === 1,
        ));
        $failedLine = collect($summary)->first(static fn (string $line): bool => str_starts_with($line, 'Failed'));
        $failedCount = $failedLine !== null && preg_match('/Failed\s*:\s*(\d+)/', $failedLine, $matches) === 1
            ? (int) $matches[1]
            : 0;

        Log::channel('single')->info('JDIH regulation sync finished', [
            'source' => $this->source ?? 'all',
            'limit' => $this->limit,
            'exit_code' => $exitCode,
            'summary' => $summary,
        ]);

        $reviewLines = array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, '[review:'),
        ));
        if ($reviewLines !== []) {
            Log::channel('single')->warning('JDIH sync has documents requiring review.', [
                'source' => $this->source ?? 'all',
                'documents' => $reviewLines,
            ]);
        }

        if ($exitCode !== 0 || $failedCount > 0) {
            $failures = array_values(array_filter(
                $lines,
                static fn (string $line): bool => str_starts_with($line, '[fail'),
            ));
            Log::channel('single')->error('Queued JDIH sync completed with errors.', [
                'source' => $this->source ?? 'all',
                'limit' => $this->limit,
                'exit_code' => $exitCode,
                'failed_count' => $failedCount,
                'failures' => $failures,
                'summary' => $summary,
                'output_tail' => array_slice($lines, -8),
            ]);

            throw new RuntimeException(sprintf(
                'JDIH sync failed (source=%s, exit=%d, failed=%d): %s',
                $this->source ?? 'all',
                $exitCode,
                $failedCount,
                implode(' | ', array_merge(array_slice($failures, 0, 3), array_slice($lines, -8))),
            ));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('single')->error('JDIH regulation sync queue job failed.', [
            'source' => $this->source ?? 'all',
            'limit' => $this->limit,
            'error' => $exception?->getMessage(),
        ]);
    }
}
