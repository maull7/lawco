<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScrapingFailureDocuments
{
    /** @param Collection<int, object> $failures */
    public function enrich(Collection $failures, ScrapingFailureSummary $summary): ?string
    {
        if ($failures->isEmpty()) {
            return null;
        }
        $requested = collect();
        foreach ($failures as $failure) {
            $failure->jdih_total = null;
            $failure->failed_documents = [];
            $failure->document_failures = $this->recordedFailures($failure->exception);
            foreach ($failure->document_failures as $document) {
                $requested->put($document['source'].':'.$document['id'], $document);
            }
            if ($failure->summary['source'] !== null) {
                foreach ($failure->summary['document_ids'] ?? [] as $id) {
                    $source = $failure->summary['source'];
                    $requested->put($source.':'.$id, ['source' => $source, 'id' => $id]);
                }
            }
        }
        $synced = collect();
        if ($requested->isNotEmpty()) {
            $synced = DB::table('jdih_sync_log')->where(fn (Builder $query) => $this->documentScope($query, $requested))
                ->get(['jdih_source', 'jdih_document_id'])->keyBy(fn (object $row): string => $row->jdih_source.':'.$row->jdih_document_id);
        }
        $metadata = collect();
        $warning = null;
        try {
            $sources = $failures->pluck('summary.source')->filter()->unique()->values()->all();
            $allSources = $failures->contains(fn (object $failure): bool => $failure->summary['all_sources']);
            $totals = DB::connection('jdih')->table('regulations')
                ->when(! $allSources, fn (Builder $query) => $query->whereIn('source', $sources))
                ->select('source')->selectRaw('COUNT(*) AS document_count')->groupBy('source')->pluck('document_count', 'source');
            foreach ($failures as $failure) {
                $failure->jdih_total = $failure->summary['all_sources'] ? (int) $totals->sum()
                    : ($failure->summary['source'] !== null ? (int) $totals->get($failure->summary['source'], 0) : null);
            }
            if ($requested->isNotEmpty()) {
                $metadata = DB::connection('jdih')->table('regulations')
                    ->where(fn (Builder $query) => $this->documentScope($query, $requested, 'source', 'document_id'))
                    ->get(['source', 'document_id', 'title', 'local_path'])
                    ->keyBy(fn (object $row): string => $row->source.':'.$row->document_id);
            }
        } catch (Throwable $exception) {
            report($exception);
            $warning = 'Database JDIH belum dapat diakses. Jumlah dan nama file dari JDIH sementara tidak tersedia; riwayat gagal tetap bisa dilihat.';
        }
        foreach ($failures as $failure) {
            $documents = collect($failure->document_failures)->keyBy(fn (array $document): string => $document['source'].':'.$document['id']);
            $source = $failure->summary['source'];
            foreach ($failure->summary['document_ids'] ?? [] as $id) {
                if ($source !== null && ! $synced->has($source.':'.$id) && ! $documents->has($source.':'.$id)) {
                    $documents->put($source.':'.$id, ['source' => $source, 'id' => $id, 'error' => null]);
                }
            }
            foreach ($documents as $key => $document) {
                $row = $metadata->get($key);
                $rawPath = trim((string) ($row?->local_path ?? ''));
                $path = $rawPath;
                if ($rawPath !== '' && ! str_starts_with($rawPath, '/')) {
                    $root = rtrim((string) config('database.connections.jdih.scraper_root', ''), '/\\');
                    $path = $root !== '' ? $root.'/'.$rawPath : '';
                }
                $failure->failed_documents[] = [
                    'id' => $document['id'], 'source' => $document['source'],
                    'title' => $row?->title ?: 'Nama dokumen tidak tersedia',
                    'filename' => $rawPath !== '' ? basename(str_replace('\\', '/', $rawPath)) : 'Nama file tidak tersedia',
                    'file_status' => $row === null ? 'Status file tidak tersedia' : (is_file($path) ? 'File tersedia di folder sumber' : 'File tidak ada di folder sumber'),
                    'status' => $synced->has($key) ? 'Sudah masuk setelah percobaan gagal'
                        : (isset($document['error']) ? 'Gagal tercatat' : 'Belum masuk; kegagalan per file tidak tercatat'),
                    'reason' => isset($document['error']) ? $summary->humanError($document['error'])
                        : 'Dokumen ini berada dalam batch yang gagal, tetapi belum ada catatan penyebab untuk file ini.',
                ];
            }
        }

        return $warning;
    }

    /** @return list<array{source: string, id: string, error: string}> */
    private function recordedFailures(string $exception): array
    {
        preg_match_all('/\[fail(?::[^\]]+)?\]\s+([^\s\/]+)\/(.*?)\s+:\s+(.*?)(?=\s+\|\s+|\r?\n|$)/s', $exception, $matches, PREG_SET_ORDER);
        $documents = [];
        foreach ($matches as $match) {
            $documents[] = ['source' => $match[1], 'id' => $match[2], 'error' => $match[3]];
        }

        return $documents;
    }

    /** @param Collection<string, array{source: string, id: string, error?: string}> $documents */
    private function documentScope(Builder $query, Collection $documents, string $sourceColumn = 'jdih_source', string $documentColumn = 'jdih_document_id'): void
    {
        foreach ($documents->groupBy('source') as $source => $rows) {
            $query->orWhere(fn (Builder $query) => $query->where($sourceColumn, $source)->whereIn($documentColumn, $rows->pluck('id')->all()));
        }
    }
}
