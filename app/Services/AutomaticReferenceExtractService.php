<?php

namespace App\Services;

use App\Jobs\AutoExtractRegulationReferences;
use App\Models\Regulation;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nightly batch that extracts related regulations from parsed regulations.
 * Mirrors AutomaticRegulationParseService: results live in job_batches.options['document_results'].
 */
class AutomaticReferenceExtractService
{
    private const BATCH_PREFIX = 'lawco-regulation-auto-extract:';

    /**
     * @param  array<int, int>  $sectorIds
     * @return Batch|null The first batch, with metadata for the entire run.
     */
    public function start(int $limit, string $source = 'manual', ?int $userId = null, array $sectorIds = []): ?Batch
    {
        $maximum = (int) config('automatic-parse.extract_maximum_limit');
        if ($limit < 1 || $limit > $maximum) {
            throw ValidationException::withMessages(['limit' => 'Limit harus antara 1 dan '.number_format($maximum, 0, ',', '.').' dokumen.']);
        }

        $sectorIds = array_values(array_unique(array_map('intval', $sectorIds)));

        return Cache::lock('regulations-auto-extract-start', 30)->block(3, function () use ($limit, $source, $userId, $sectorIds): ?Batch {
            if ($this->activeBatch()) {
                throw ValidationException::withMessages(['limit' => 'Batch ekstrak otomatis sebelumnya masih berjalan.']);
            }
            $date = now(config('automatic-parse.timezone'))->format('Y-m-d');
            $name = self::BATCH_PREFIX.$source.':'.$date;
            if ($source === 'scheduled' && DB::table('job_batches')->where(fn ($query) => $query->where('name', $name)->orWhere('name', 'like', $name.':%'))->exists()) {
                return null;
            }

            $ids = $this->candidateIds($limit, sectorIds: $sectorIds);
            if ($ids === []) {
                return null;
            }

            $runId = (string) Str::uuid();
            $chunks = array_chunk($ids, (int) config('automatic-parse.batch_size'));
            $sectorNames = Sector::query()->whereIn('id', $sectorIds)->orderBy('name')->pluck('name')->all();
            $creator = $userId ? User::find($userId)?->name : null;
            $firstBatch = null;
            foreach ($chunks as $index => $chunk) {
                $batch = Bus::batch(array_map(fn (int $id): AutoExtractRegulationReferences => new AutoExtractRegulationReferences($id), $chunk))
                    ->name($name.':'.$runId.':'.($index + 1))->allowFailures()
                    ->onConnection(config('automatic-parse.connection'))
                    ->onQueue(config('automatic-parse.extract_queue'))
                    ->withOption('run_id', $runId)
                    ->withOption('run_total', count($ids))
                    ->withOption('batch_index', $index + 1)
                    ->withOption('batch_count', count($chunks))
                    ->withOption('regulation_ids', $chunk)
                    ->withOption('requested_limit', $limit)
                    ->withOption('source', $source)
                    ->withOption('sector_ids', $sectorIds)
                    ->withOption('sector_name', $sectorNames !== [] ? implode(', ', $sectorNames) : 'Semua Sektor')
                    ->withOption('created_by', $userId)
                    ->withOption('created_by_name', $creator)
                    ->withOption('document_results', $this->initialResults($chunk))
                    ->finally(static function (Batch $batch): void {
                        app(BatchRepository::class)->markAsFinished($batch->id);
                        Log::info('Batch ekstrak peraturan terkait otomatis selesai.', [
                            'batch_id' => $batch->id,
                            'documents' => $batch->totalJobs,
                            'failed' => $batch->failedJobs,
                        ]);
                    })->dispatch();
                $firstBatch ??= $batch;
            }

            return $firstBatch;
        });
    }

    /**
     * Fully parsed regulations that were never extracted (manually or automatically).
     * Errored extractions are not retried automatically to avoid paying for the same failure nightly.
     *
     * @param  array<int, int>  $sectorIds
     * @return array<int, int>
     */
    public function candidateIds(int $limit, array $sectorIds = []): array
    {
        return Regulation::query()
            ->when($sectorIds !== [], fn (Builder $query) => $query->whereIn('sector_id', $sectorIds))
            ->where('parse_status', 'complete')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('ai_job_status')
                ->where('ai_job_status.model_type', (new Regulation)->getMorphClass())
                ->whereColumn('ai_job_status.model_id', 'regulations.id')
                ->where('ai_job_status.action', 'extract'))
            ->orderBy('id')->limit($limit)->pluck('id')->all();
    }

    public function activeBatch(): ?Batch
    {
        $id = DB::table('job_batches')->where('name', 'like', self::BATCH_PREFIX.'%')
            ->whereNull('cancelled_at')->whereNull('finished_at')
            ->whereColumn('pending_jobs', '>', 'failed_jobs')
            ->orderByDesc('created_at')->value('id');

        return $id ? Bus::findBatch($id) : null;
    }

    /** @return array{sector: string, batches: int, total: int, pending: int, complete: int, failed: int, references: int, linked: int, percent: int, active: bool, failed_documents: array<int, array<string, mixed>>}|null */
    public function summary(): ?array
    {
        $batch = $this->activeBatch();
        if (! $batch) {
            $id = DB::table('job_batches')->where('name', 'like', self::BATCH_PREFIX.'%')->orderByDesc('created_at')->value('id');
            $batch = $id ? Bus::findBatch($id) : null;
        }
        if (! $batch) {
            return null;
        }
        $runId = $batch->options['run_id'] ?? null;
        $rows = DB::table('job_batches')->when($runId,
            fn ($query) => $query->where('name', 'like', self::BATCH_PREFIX.'%:'.$runId.':%'),
            fn ($query) => $query->where('id', $batch->id))
            ->get();
        $results = $rows->flatMap(fn (object $row): array => array_values($this->decodeOptions($row->options)['document_results'] ?? []));
        $rows = $rows->map(fn (object $row): array => $this->historyRow($row));
        $total = (int) $rows->sum('total');
        $pending = (int) $rows->sum('pending');

        return [
            'sector' => $batch->options['sector_name'] ?? 'Semua Sektor',
            'batches' => $rows->count(),
            'total' => $total,
            'pending' => $pending,
            'complete' => (int) $rows->sum('complete'),
            'failed' => (int) $rows->sum('failed'),
            'references' => (int) $rows->sum('references'),
            'linked' => (int) $rows->sum('linked'),
            'percent' => (int) round((($total - $pending) / max(1, $total)) * 100),
            'active' => $rows->contains(fn (array $row): bool => $row['active']),
            'failed_documents' => $results->where('status', 'failed')->values()->all(),
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function initialResults(array $ids): array
    {
        return Regulation::query()->whereIn('id', $ids)->with('sector')->get(['id', 'regulation_number', 'title', 'sector_id'])
            ->mapWithKeys(fn (Regulation $regulation): array => [$regulation->id => [
                'id' => $regulation->id, 'number' => $regulation->regulation_number, 'title' => $regulation->title,
                'sector' => $regulation->sector?->name, 'sector_id' => $regulation->sector_id,
                'status' => 'queued', 'references' => null, 'linked' => null,
                'started_at' => null, 'finished_at' => null, 'error' => null,
            ]])->all();
    }

    public function recordDocumentResult(?string $batchId, int $regulationId, string $status, ?string $error = null, ?int $references = null, ?int $linked = null): void
    {
        if (! $batchId) {
            return;
        }
        $connection = DB::connection(config('queue.batching.database'));
        $connection->transaction(function () use ($connection, $batchId, $regulationId, $status, $error, $references, $linked): void {
            $row = $connection->table('job_batches')->where('id', $batchId)->where('name', 'like', self::BATCH_PREFIX.'%')->lockForUpdate()->first();
            if (! $row) {
                return;
            }
            $options = $this->decodeOptions($row->options);
            $result = $options['document_results'][$regulationId] ?? null;
            if (! $result || in_array($result['status'], ['complete', 'failed', 'skipped', 'cancelled'], true)) {
                return;
            }
            $result['status'] = $status;
            $result['started_at'] ??= $status === 'processing' ? now()->toIso8601String() : null;
            $result['finished_at'] = $status !== 'processing' ? now()->toIso8601String() : null;
            $result['references'] = $references ?? $result['references'];
            $result['linked'] = $linked ?? $result['linked'];
            $result['error'] = $error ? mb_substr($error, 0, 500) : null;
            $options['document_results'][$regulationId] = $result;
            $serialized = serialize($options);
            $connection->table('job_batches')->where('id', $batchId)->update([
                'options' => $connection->getDriverName() === 'pgsql' ? base64_encode($serialized) : $serialized,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function decodeOptions(string $serialized): array
    {
        // ponytail: copied from AutomaticRegulationParseService, extract a shared trait if a third automatic batch appears
        if (DB::connection(config('queue.batching.database'))->getDriverName() === 'pgsql' && ! str_contains($serialized, ':')) {
            $serialized = base64_decode($serialized);
        }
        $options = unserialize($serialized);

        return is_array($options) ? $options : [];
    }

    public function history(): LengthAwarePaginator
    {
        return DB::connection(config('queue.batching.database'))->table('job_batches')
            ->where('name', 'like', self::BATCH_PREFIX.'%')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->through(fn (object $row): array => $this->historyRow($row));
    }

    /** @return array<string, mixed> */
    public function historyDetail(string $batchId): array
    {
        $row = DB::connection(config('queue.batching.database'))->table('job_batches')
            ->where('id', $batchId)->where('name', 'like', self::BATCH_PREFIX.'%')->first();
        abort_unless($row, 404);
        $options = $this->decodeOptions($row->options);

        return ['batch' => $this->historyRow($row), 'documents' => array_values($options['document_results'] ?? [])];
    }

    /** @return array<string, mixed> */
    private function historyRow(object $row): array
    {
        $options = $this->decodeOptions($row->options);
        $results = collect($options['document_results'] ?? []);
        $counts = $results->countBy('status');
        $pending = max(0, $row->pending_jobs - $row->failed_jobs);
        $active = ! $row->cancelled_at && ! $row->finished_at && $pending > 0;
        $complete = (int) ($counts['complete'] ?? 0);
        $failed = max((int) $row->failed_jobs, (int) ($counts['failed'] ?? 0));
        $status = match (true) {
            $row->cancelled_at !== null => 'Dibatalkan',
            $active => 'Berjalan',
            $complete === $row->total_jobs => 'Berhasil',
            $failed === $row->total_jobs => 'Gagal',
            $failed > 0 || ($counts['skipped'] ?? 0) > 0 || ($counts['cancelled'] ?? 0) > 0 => 'Selesai dengan catatan',
            default => 'Selesai',
        };

        return [
            'id' => $row->id,
            'source' => ($options['source'] ?? null) === 'scheduled' ? 'Jadwal malam' : 'Tombol manual',
            'started_at' => Carbon::createFromTimestamp($row->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'finished_at' => $row->finished_at ? Carbon::createFromTimestamp($row->finished_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') : null,
            'creator' => $options['created_by_name'] ?? 'Sistem',
            'sector' => $options['sector_name'] ?? 'Semua Sektor',
            'limit' => $options['requested_limit'] ?? $row->total_jobs,
            'batch_index' => $options['batch_index'] ?? 1,
            'batch_count' => $options['batch_count'] ?? 1,
            'total' => $row->total_jobs, 'pending' => $pending,
            'complete' => $complete, 'failed' => $failed,
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'references' => (int) $results->sum('references'),
            'linked' => (int) $results->sum('linked'),
            'percent' => (int) round((($row->total_jobs - $pending) / max(1, $row->total_jobs)) * 100),
            'active' => $active, 'status' => $status,
        ];
    }
}
