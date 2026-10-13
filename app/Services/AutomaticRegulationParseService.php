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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AutomaticRegulationParseService
{
    private const BATCH_PREFIX = 'lawco-regulation-auto-parse:';

    public function scheduledSectorId(): ?int
    {
        $sectorId = Setting::get('automatic_parse_sector_id');

        return $sectorId ? (int) $sectorId : null;
    }

    public function scheduledSector(): ?Sector
    {
        $sectorId = $this->scheduledSectorId();

        return $sectorId ? Sector::query()->where('is_active', true)->find($sectorId) : null;
    }

    public function saveScheduledSector(int $sectorId): void
    {
        Setting::updateOrCreate(['key' => 'automatic_parse_sector_id'], ['value' => (string) $sectorId]);
    }

    public function start(int $limit, string $source = 'manual', ?int $userId = null, ?int $sectorId = null): ?Batch
    {
        if ($limit < 1 || $limit > config('automatic-parse.maximum_limit')) {
            throw ValidationException::withMessages(['limit' => 'Limit harus antara 1 dan 1000 dokumen.']);
        }

        return Cache::lock('regulations-auto-parse-start', 30)->block(3, function () use ($limit, $source, $userId, $sectorId): ?Batch {
            if ($this->activeBatch()) {
                throw ValidationException::withMessages(['limit' => 'Batch parse otomatis sebelumnya masih berjalan.']);
            }
            $date = now(config('automatic-parse.timezone'))->format('Y-m-d');
            $name = self::BATCH_PREFIX.$source.':'.$date;
            if ($source === 'scheduled' && DB::table('job_batches')->where('name', $name)->exists()) {
                return null;
            }

            $ids = $this->candidateIds($limit, $sectorId);
            if ($ids === []) {
                return null;
            }

            return Bus::batch(array_map(fn (int $id): AutoParseRegulation => new AutoParseRegulation($id), $ids))
                ->name($name)->allowFailures()
                ->onConnection(config('automatic-parse.connection'))
                ->onQueue(config('automatic-parse.queue'))
                ->withOption('regulation_ids', $ids)
                ->withOption('requested_limit', $limit)
                ->withOption('source', $source)
                ->withOption('sector_id', $sectorId)
                ->withOption('sector_name', $sectorId ? Sector::findOrFail($sectorId)->name : 'Semua Sektor')
                ->withOption('created_by', $userId)
                ->withOption('created_by_name', $userId ? User::find($userId)?->name : null)
                ->withOption('document_results', $this->initialResults($ids))
                ->finally(static function (Batch $batch): void {
                    app(BatchRepository::class)->markAsFinished($batch->id);
                    Log::info('Batch parse otomatis regulasi selesai.', [
                        'batch_id' => $batch->id,
                        'documents' => $batch->totalJobs,
                        'failed' => $batch->failedJobs,
                    ]);
                })->dispatch();
        });
    }

    /** @return array<int, int> */
    public function candidateIds(int $limit, ?int $sectorId = null): array
    {
        $ids = [];
        $candidates = Regulation::query()
            ->when($sectorId, fn (Builder $query) => $query->where('sector_id', $sectorId))
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

    /** @return array{sector: string, total: int, pending: int, complete: int, incomplete: int, failed: int, percent: int, active: bool}|null */
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
        $results = $batch->options['document_results'] ?? [];
        $counts = collect($results)->countBy('status');
        $pending = max(0, $batch->pendingJobs - $batch->failedJobs);

        return [
            'sector' => $batch->options['sector_name'] ?? 'Semua Sektor',
            'total' => $batch->totalJobs,
            'pending' => $pending,
            'complete' => (int) ($counts['complete'] ?? 0),
            'incomplete' => (int) ($counts['incomplete'] ?? 0),
            'failed' => max($batch->failedJobs, (int) ($counts['failed'] ?? 0)),
            'percent' => (int) round((($batch->totalJobs - $pending) / max(1, $batch->totalJobs)) * 100),
            'active' => ! $batch->cancelled() && ! $batch->finished() && $pending > 0,
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
                'sector' => $regulation->sector?->name,
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
            'total' => $row->total_jobs, 'pending' => $pending,
            'complete' => $complete, 'incomplete' => $incomplete, 'failed' => $failed,
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'percent' => (int) round((($row->total_jobs - $pending) / max(1, $row->total_jobs)) * 100),
            'active' => $active, 'status' => $status, 'has_results' => $results !== [],
        ];
    }
}
