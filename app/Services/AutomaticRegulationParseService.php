<?php

namespace App\Services;

use App\Jobs\AutoParseRegulation;
use App\Models\Regulation;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutomaticRegulationParseService
{
    private const BATCH_PREFIX = 'lawco-regulation-auto-parse:';

    /** @return array<int, int> */
    public function scheduledSectorIds(): array
    {
        $stored = Setting::get('automatic_parse_sector_ids');
        if ($stored !== null) {
            return array_values(array_unique(array_map('intval', json_decode($stored, true, flags: JSON_THROW_ON_ERROR))));
        }
        $legacy = Setting::get('automatic_parse_sector_id');

        return $legacy ? [(int) $legacy] : [];
    }

    /** @return Collection<int, Sector> */
    public function scheduledSectors(): Collection
    {
        return Sector::query()->where('is_active', true)->whereIn('id', $this->scheduledSectorIds())->orderBy('name')->get();
    }

    /** @param array<int, int> $sectorIds */
    public function saveScheduledSectors(array $sectorIds): void
    {
        Setting::updateOrCreate(['key' => 'automatic_parse_sector_ids'], [
            'value' => json_encode(array_values(array_unique(array_map('intval', $sectorIds))), JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<int, int>  $sectorIds
     * @return Batch|null The first batch, with metadata for the entire run.
     */
    public function start(int $limit, string $source = 'manual', ?int $userId = null, array $sectorIds = []): ?Batch
    {
        if ($limit < 1 || $limit > config('automatic-parse.maximum_limit')) {
            throw ValidationException::withMessages(['limit' => 'Limit harus antara 1 dan 3000 dokumen.']);
        }

        $sectorIds = array_values(array_unique(array_map('intval', $sectorIds)));

        return Cache::lock('regulations-auto-parse-start', 30)->block(3, function () use ($limit, $source, $userId, $sectorIds): ?Batch {
            if ($this->activeBatch()) {
                throw ValidationException::withMessages(['limit' => 'Batch parse otomatis sebelumnya masih berjalan.']);
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
                $batch = Bus::batch(array_map(fn (int $id): AutoParseRegulation => new AutoParseRegulation($id), $chunk))
                    ->name($name.':'.$runId.':'.($index + 1))->allowFailures()
                    ->onConnection(config('automatic-parse.connection'))
                    ->onQueue(config('automatic-parse.queue'))
                    ->withOption('run_id', $runId)
                    ->withOption('run_total', count($ids))
                    ->withOption('batch_index', $index + 1)
                    ->withOption('batch_count', count($chunks))
                    ->withOption('regulation_ids', $chunk)
                    ->withOption('requested_limit', $limit)
                    ->withOption('source', $source)
                    ->withOption('sector_ids', $sectorIds)
                    ->withOption('sector_id', count($sectorIds) === 1 ? $sectorIds[0] : null)
                    ->withOption('sector_name', $sectorNames !== [] ? implode(', ', $sectorNames) : 'Semua Sektor')
                    ->withOption('created_by', $userId)
                    ->withOption('created_by_name', $creator)
                    ->withOption('document_results', $this->initialResults($chunk))
                    ->finally(static function (Batch $batch): void {
                        app(BatchRepository::class)->markAsFinished($batch->id);
                        Log::info('Batch parse otomatis regulasi selesai.', [
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
     * @param  array<int, int>  $sectorIds
     * @return array<int, int>
     */
    public function candidateIds(int $limit, array $sectorIds = []): array
    {
        $ids = [];
        $candidates = Regulation::query()
            ->when($sectorIds !== [], fn (Builder $query) => $query->whereIn('sector_id', $sectorIds))
            ->where(fn (Builder $query) => $query->where('parse_status', 'not_parsed')->orWhereNull('parse_status'))
            ->where('file_path', 'like', '%.pdf')
            ->select(['id', 'file_path'])->lazyById(100);

        foreach ($candidates as $regulation) {
            if (Storage::disk('public')->exists($regulation->file_path)) {
                $ids[] = $regulation->id;
            }
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    public function activeBatch(): ?Batch
    {
        $id = DB::table('job_batches')->where('name', 'like', self::BATCH_PREFIX.'%')
            ->whereNull('cancelled_at')->whereNull('finished_at')
            ->whereColumn('pending_jobs', '>', 'failed_jobs')
            ->orderByDesc('created_at')->value('id');

        return $id ? Bus::findBatch($id) : null;
    }

    /** @return array{sector: string, batches: int, total: int, pending: int, complete: int, incomplete: int, failed: int, percent: int, active: bool, sectors: array<int, array<string, mixed>>, failed_documents: array<int, array<string, mixed>>, coverage: array<string, mixed>}|null */
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
        $sectors = $this->sectorBreakdown($results, array_map('intval', $batch->options['sector_ids'] ?? []));
        $total = (int) $rows->sum('total');
        $pending = (int) $rows->sum('pending');

        return [
            'sector' => $batch->options['sector_name'] ?? 'Semua Sektor',
            'batches' => $rows->count(),
            'total' => $total,
            'pending' => $pending,
            'complete' => (int) $rows->sum('complete'),
            'incomplete' => (int) $rows->sum('incomplete'),
            'failed' => (int) $rows->sum('failed'),
            'percent' => (int) round((($total - $pending) / max(1, $total)) * 100),
            'active' => $rows->contains(fn (array $row): bool => $row['active']),
            'sectors' => $sectors,
            'coverage' => $this->coverage($sectors),
            'failed_documents' => $this->unresolvedFailures($results),
        ];
    }

    /**
     * Per-sector run results plus live coverage of every regulation in the sector.
     *
     * @param  SupportCollection<int, array<string, mixed>>  $results
     * @param  array<int, int>  $selectedSectorIds
     * @return array<int, array{name: string, processed: int, complete: int, incomplete: int, failed: int, sector_total: int|null, sector_parsed: int|null, remaining: int|null, coverage_percent: int|null}>
     */
    private function sectorBreakdown(SupportCollection $results, array $selectedSectorIds): array
    {
        $sectorIds = collect($selectedSectorIds)->merge($results->pluck('sector_id')->filter())->unique()->values()->all();
        $live = Regulation::query()->whereIn('sector_id', $sectorIds)->groupBy('sector_id')
            ->selectRaw("sector_id, count(*) as total, sum(case when parse_status = 'complete' then 1 else 0 end) as parsed, sum(case when parse_status = 'not_parsed' or parse_status is null then 1 else 0 end) as remaining")
            ->get()->keyBy('sector_id');
        $names = Sector::query()->whereIn('id', $sectorIds)->pluck('name', 'id');

        return $results->groupBy(fn (array $result): string => isset($result['sector_id']) ? 'id:'.$result['sector_id'] : 'name:'.($result['sector'] ?? 'Tanpa Sektor'))
            ->union(collect($selectedSectorIds)->mapWithKeys(fn (int $id): array => ['id:'.$id => collect()]))
            ->map(function (SupportCollection $items, string $key) use ($live, $names): array {
                $counts = $items->countBy('status');
                $sectorId = str_starts_with($key, 'id:') ? (int) substr($key, 3) : null;
                $sector = $sectorId ? $live->get($sectorId) : null;
                $sectorTotal = $sectorId ? (int) ($sector->total ?? 0) : null;
                $sectorParsed = $sectorId ? (int) ($sector->parsed ?? 0) : null;

                return [
                    'name' => $items->first()['sector'] ?? $names[$sectorId] ?? substr($key, 5),
                    'processed' => $items->count(),
                    'complete' => (int) ($counts['complete'] ?? 0),
                    'incomplete' => (int) ($counts['incomplete'] ?? 0),
                    'failed' => (int) ($counts['failed'] ?? 0),
                    'sector_total' => $sectorTotal,
                    'sector_parsed' => $sectorParsed,
                    'remaining' => $sectorId ? (int) ($sector->remaining ?? 0) : null,
                    'coverage_percent' => $sectorId ? (int) floor($sectorParsed / max(1, $sectorTotal) * 100) : null,
                ];
            })->sortBy('name')->values()->all();
    }

    /**
     * Failed run documents that have not since been parsed successfully (e.g. by a manual parse).
     *
     * @param  SupportCollection<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function unresolvedFailures(SupportCollection $results): array
    {
        $failed = $results->where('status', 'failed');
        $parsedIds = Regulation::query()->whereIn('id', $failed->pluck('id'))->where('parse_status', 'complete')->pluck('id')->all();

        return $failed->whereNotIn('id', $parsedIds)->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sectors
     * @return array{sector_total: int, sector_parsed: int, remaining: int, percent: int, covers_all: bool}
     */
    private function coverage(array $sectors): array
    {
        $known = collect($sectors)->whereNotNull('sector_total');
        $total = (int) $known->sum('sector_total');
        $parsed = (int) $known->sum('sector_parsed');
        $remaining = (int) $known->sum('remaining');

        return [
            'sector_total' => $total, 'sector_parsed' => $parsed, 'remaining' => $remaining,
            'percent' => (int) floor($parsed / max(1, $total) * 100),
            'covers_all' => $known->isNotEmpty() && $remaining === 0,
        ];
    }

    /** @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function initialResults(array $ids): array
    {
        return Regulation::query()->whereIn('id', $ids)->with('sector')->get(['id', 'regulation_number', 'title', 'sector_id'])
            ->mapWithKeys(fn (Regulation $regulation): array => [$regulation->id => [
                'id' => $regulation->id, 'number' => $regulation->regulation_number, 'title' => $regulation->title,
                'sector' => $regulation->sector?->name, 'sector_id' => $regulation->sector_id,
                'status' => 'queued', 'percent' => 0, 'parsed_pages' => 0, 'total_pages' => null,
                'started_at' => null, 'finished_at' => null, 'error' => null,
            ]])->all();
    }

    public function recordDocumentResult(?string $batchId, int $regulationId, string $status, ?string $error = null): void
    {
        if (! $batchId) {
            return;
        }
        $connection = DB::connection(config('queue.batching.database'));
        $connection->transaction(function () use ($connection, $batchId, $regulationId, $status, $error): void {
            $row = $connection->table('job_batches')->where('id', $batchId)->where('name', 'like', self::BATCH_PREFIX.'%')->lockForUpdate()->first();
            if (! $row) {
                return;
            }
            $options = $this->decodeOptions($row->options);
            $result = $options['document_results'][$regulationId] ?? null;
            if (! $result || in_array($result['status'], ['complete', 'incomplete', 'failed', 'skipped', 'cancelled'], true)) {
                return;
            }
            $regulation = Regulation::find($regulationId);
            $stats = $regulation?->parse_stats ?? [];
            $terminal = $status !== 'parsing';
            $result['status'] = $status;
            $result['started_at'] ??= $status === 'parsing' ? now()->toIso8601String() : null;
            $result['finished_at'] = $terminal ? now()->toIso8601String() : null;
            if (($stats['auto_parse_batch_id'] ?? null) === $batchId) {
                $result['percent'] = $stats['percent_parsed'] ?? 0;
                $result['parsed_pages'] = $stats['parsed_pages'] ?? 0;
                $result['total_pages'] = $stats['total_pages'] ?? null;
            }
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
        $results = $options['document_results'] ?? [];
        $counts = collect($results)->countBy('status');
        $pending = max(0, $row->pending_jobs - $row->failed_jobs);
        $active = ! $row->cancelled_at && ! $row->finished_at && $pending > 0;
        $complete = (int) ($counts['complete'] ?? 0);
        $incomplete = (int) ($counts['incomplete'] ?? 0);
        $failed = max((int) $row->failed_jobs, (int) ($counts['failed'] ?? 0));
        $status = match (true) {
            $row->cancelled_at !== null => 'Dibatalkan',
            $active => 'Berjalan',
            $complete === $row->total_jobs => 'Berhasil',
            $failed === $row->total_jobs => 'Gagal',
            $incomplete > 0 || $failed > 0 || ($counts['skipped'] ?? 0) > 0 || ($counts['cancelled'] ?? 0) > 0 => 'Selesai dengan catatan',
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
            'complete' => $complete, 'incomplete' => $incomplete, 'failed' => $failed,
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'percent' => (int) round((($row->total_jobs - $pending) / max(1, $row->total_jobs)) * 100),
            'active' => $active, 'status' => $status, 'has_results' => $results !== [],
        ];
    }
}
