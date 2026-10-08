<?php

namespace App\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class SyncJdihRegulations implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public int $timeout = 900;

    public int $uniqueFor = 86400;

    /** @var list<string>|null */
    public ?array $documentIds = null;

    public bool $fromFolder = false;

    /** @param list<string>|null $documentIds */
    public function __construct(
        public ?string $source = null,
        public int $limit = 0,
        ?array $documentIds = null,
        bool $fromFolder = false,
    ) {
        $this->documentIds = $documentIds;
        $this->fromFolder = $fromFolder;
        $this->onConnection('redis');
        $this->onQueue('jdih');
    }

    public function uniqueId(): string
    {
        return sprintf(
            'jdih-regulations-sync:%s:%s',
            $this->source ?? 'all',
            $this->documentIds === null ? 'planner' : hash('sha256', implode("\n", $this->documentIds)),
        );
    }

    public function displayName(): string
    {
        return sprintf('JDIH sync (%s, %s)', $this->source ?? 'all', $this->documentIds === null ? 'planner' : count($this->documentIds).' documents');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('jdih-regulations-sync'))->releaseAfter(30)->expireAfter(1200)->shared()];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(): void
    {
        if ($this->documentIds === []) {
            return;
        }
        $startedAt = microtime(true);
        $context = $this->logContext();
        Log::channel('single')->info('JDIH regulation sync started.', $context);
        $output = new BufferedOutput;
        $parameters = ['--limit' => $this->limit, '--only-pending' => true];
        if ($this->fromFolder) {
            $parameters['--from-folder'] = true;
        }
        if ($this->documentIds === null) {
            $parameters['--queue'] = true;
        } else {
            $parameters['--document'] = $this->documentIds;
        }

        if ($this->source !== null) {
            $parameters['--source'] = $this->source;
        }

        try {
            $exitCode = Artisan::call('jdih:sync', $parameters, $output);
        } catch (Throwable $exception) {
            Log::channel('single')->error('JDIH regulation sync interrupted.', array_merge($context, [
                'duration_seconds' => round(microtime(true) - $startedAt, 3),
                'error' => $exception->getMessage(),
                'exception' => $exception,
                'output_tail' => array_slice(explode("\n", trim($output->fetch())), -20),
            ]));

            throw $exception;
        }
        $context['duration_seconds'] = round(microtime(true) - $startedAt, 3);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output->fetch()))));
        if ($this->documentIds === null && $exitCode === 0 && $context['job_id'] !== null) {
            $result = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '[planner:result] '));
            if ($result !== null) {
                Cache::put('jdih-planner-result:'.$context['job_id'],
                    $this->displayName().' — '.substr($result, strlen('[planner:result] ')), now()->addDay());
            }
        }
        $summary = array_values(array_filter(
            $lines,
            static fn (string $line): bool => preg_match('/^(Total source|Available PDF|Missing PDF|Imported|Already synced|Failed|Needs review|Pending|Folder PDFs|Source PDFs|Queued|Unmatched PDF|Source metadata|Unsynced documents|Missing source PDFs)\s*:/', $line) === 1,
        ));
        $failedLine = collect($summary)->first(static fn (string $line): bool => str_starts_with($line, 'Failed'));
        $failedCount = $failedLine !== null && preg_match('/Failed\s*:\s*(\d+)/', $failedLine, $matches) === 1
            ? (int) $matches[1]
            : 0;

        $reviewLines = array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, '[review:'),
        ));
        $statistics = [];
        foreach ($summary as $line) {
            if (preg_match('/^([A-Za-z ]+?)\s*:\s*(\d+)/', $line, $matches)) {
                $statistics[trim($matches[1])] = (int) $matches[2];
            }
        }
        $outcome = match (true) {
            $exitCode !== 0 || $failedCount > 0 => 'failed',
            collect($lines)->contains(fn (string $line): bool => str_starts_with($line, '[done:no_files]')) => 'no_files',
            collect($lines)->contains(fn (string $line): bool => str_starts_with($line, '[done:no_candidates]')) => 'no_candidates',
            $this->documentIds === null => 'queued',
            $reviewLines !== [] => 'needs_review',
            default => 'completed',
        };
        Log::channel('single')->info('JDIH regulation sync finished', array_merge($context, [
            'exit_code' => $exitCode,
            'outcome' => $outcome,
            'statistics' => $statistics,
            'summary' => $summary,
            'output_tail' => array_slice($lines, -8),
        ]));
        if ($reviewLines !== []) {
            Log::channel('single')->warning('JDIH sync has documents requiring review.', array_merge($context, [
                'documents' => $reviewLines,
            ]));
        }

        if ($exitCode !== 0 || $failedCount > 0) {
            $failures = array_values(array_filter(
                $lines,
                static fn (string $line): bool => str_starts_with($line, '[fail'),
            ));
            Log::channel('single')->error('Queued JDIH sync completed with errors.', array_merge($context, [
                'exit_code' => $exitCode,
                'failed_count' => $failedCount,
                'failures' => $failures,
                'summary' => $summary,
                'output_tail' => array_slice($lines, -8),
            ]));

            throw new RuntimeException(sprintf(
                'JDIH sync failed (source=%s, exit=%d, failed=%d): %s',
                $this->source ?? 'all',
                $exitCode,
                $failedCount,
                implode(' | ', array_merge($failures, array_slice($lines, -8))),
            ));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('single')->error('JDIH regulation sync queue job failed.', array_merge($this->logContext(), [
            'error' => $exception?->getMessage(),
            'exception' => $exception,
        ]));
    }

    /**
     * @return array{source: string, limit: int, from_folder: bool, batch_id: string, document_ids: list<string>|null, document_count: int|null, job_id: string|null, queue: string, connection: string, timeout_seconds: int}
     */
    private function logContext(): array
    {
        return [
            'source' => $this->source ?? 'all',
            'limit' => $this->limit,
            'from_folder' => $this->fromFolder,
            'batch_id' => $this->uniqueId(),
            'document_ids' => $this->documentIds,
            'document_count' => $this->documentIds === null ? null : count($this->documentIds),
            'job_id' => $this->job !== null ? (string) $this->job->getJobId() : null,
            'queue' => $this->queue ?? 'jdih',
            'connection' => $this->connection ?? 'redis',
            'timeout_seconds' => $this->timeout,
        ];
    }
}
