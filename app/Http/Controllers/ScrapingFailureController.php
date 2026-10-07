<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScrapingFailureIndexRequest;
use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use App\Models\Sector;
use App\Services\ScrapingFailureDocuments;
use App\Services\ScrapingFailureSummary;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ScrapingFailureController extends Controller
{
    public function index(ScrapingFailureIndexRequest $request, ScrapingFailureSummary $summary, ScrapingFailureDocuments $documents): View
    {
        $filters = $request->validated();
        $targets = JdihTarget::query()->with('sector')->get()->keyBy('source');
        $sectors = Sector::query()->orderBy('name')->get(['id', 'name']);
        $query = DB::table('failed_jobs')->where('queue', 'jdih')
            ->where('payload', 'like', '%SyncJdihRegulations%');
        if (! empty($filters['sector_id'])) {
            $sources = $targets->where('sector_id', (int) $filters['sector_id'])->keys()->all();
            $query->where(fn (Builder $query) => $this->filterSources($query, $sources));
        }
        $keyword = trim($filters['q'] ?? '');
        if ($keyword !== '') {
            $sources = $targets->filter(fn (JdihTarget $target): bool => str_contains(mb_strtolower($target->name.' '.$target->sector?->name), mb_strtolower($keyword)))->keys()->all();
            $errorTerms = $summary->searchTerms($keyword);
            $query->where(function (Builder $query) use ($keyword, $sources, $errorTerms): void {
                $pattern = '%'.$this->escapeLike($keyword).'%';
                $query->whereRaw("payload LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("exception LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhere('uuid', $keyword);
                if ($sources !== []) {
                    $query->orWhere(fn (Builder $query) => $this->filterSources($query, $sources));
                }
                foreach ($errorTerms as $term) {
                    $query->orWhereRaw("exception LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($term).'%']);
                }
            });
        }
        $failures = $query->orderByDesc('failed_at')->orderByDesc('id')
            ->paginate(20, ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->withQueryString();
        $failures->getCollection()->transform(function (object $failure) use ($summary, $targets): object {
            $payload = json_decode($failure->payload, true);
            $failure->job_name = is_array($payload) && is_string($payload['displayName'] ?? null)
                ? $payload['displayName'] : 'Sinkronisasi JDIH';
            $failure->summary = $summary->describe($failure);
            $source = $failure->summary['source'];
            $target = $targets->get($source);
            $failure->source_name = $target?->name ?? $source ?? 'Semua sumber';
            $failure->sector_name = $target?->sector?->name ?? 'Belum dipetakan';

            return $failure;
        });
        $batches = $failures->getCollection()->filter(fn (object $failure): bool => $failure->summary['source'] !== null && $failure->summary['document_ids'] !== null);
        $synced = collect();
        if ($batches->isNotEmpty()) {
            $synced = DB::table('jdih_sync_log')->where(function (Builder $query) use ($batches): void {
                foreach ($batches as $failure) {
                    $query->orWhere(fn (Builder $query) => $query->where('jdih_source', $failure->summary['source'])
                        ->whereIn('jdih_document_id', $failure->summary['document_ids']));
                }
            })->get(['jdih_source', 'jdih_document_id'])->keyBy(fn (object $log): string => $log->jdih_source.':'.$log->jdih_document_id);
        }
        foreach ($failures as $failure) {
            $info = $failure->summary;
            $failure->imported_count = $info['imported'];
            if ($info['document_ids'] !== null && $info['source'] !== null) {
                $failure->imported_count = collect($info['document_ids'])->unique()
                    ->filter(fn (string $id): bool => $synced->has($info['source'].':'.$id))->count();
            }
            $failure->remaining_count = $info['total'] !== null && $failure->imported_count !== null
                ? max(0, $info['total'] - $failure->imported_count) : null;
        }

        $jdihWarning = $documents->enrich($failures->getCollection(), $summary);

        return view('scraping-failures.index', compact('failures', 'sectors', 'filters', 'jdihWarning'));
    }

    /** @param list<string> $sources */
    private function filterSources(Builder $query, array $sources): void
    {
        if ($sources === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        foreach ($sources as $source) {
            $serialized = 's:6:"source";'.serialize($source);
            $encoded = substr(json_encode($serialized), 1, -1);
            $query->orWhereRaw("payload LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($encoded).'%'])
                ->orWhereRaw("payload LIKE ? ESCAPE '!'", ['%'.$this->escapeLike('JDIH sync ('.$source.',').'%']);
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    public function retry(string $uuid): RedirectResponse
    {
        try {
            $retried = DB::transaction(function () use ($uuid): bool {
                $failure = DB::table('failed_jobs')->where('uuid', $uuid)
                    ->where('queue', 'jdih')->lockForUpdate()->first();
                $payload = $failure ? json_decode($failure->payload, true) : null;

                if (! is_array($payload) || ($payload['data']['commandName'] ?? null) !== SyncJdihRegulations::class
                    || ! is_string($payload['data']['command'] ?? null)) {
                    return false;
                }

                $job = @unserialize($payload['data']['command'], ['allowed_classes' => [SyncJdihRegulations::class]]);
                if (! $job instanceof SyncJdihRegulations) {
                    return false;
                }
                $job->fromFolder = true;
                $job->documentIds = null;
                $payload['data']['command'] = serialize($job);
                $payload['displayName'] = $job->displayName();
                DB::table('failed_jobs')->where('uuid', $uuid)->update(['payload' => json_encode($payload)]);

                if (Artisan::call('queue:retry', ['id' => [$uuid], '--no-interaction' => true]) !== 0) {
                    throw new RuntimeException('Retry antrean JDIH gagal.');
                }

                return true;
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('scraping-failures.index')
                ->with('error', 'Gagal memasukkan proses ke antrean. Silakan coba lagi; riwayat gagal tetap tersimpan.');
        }

        return redirect()->route('scraping-failures.index')->with(
            $retried ? 'success' : 'error',
            $retried ? 'Retry dimasukkan ke antrean. File PDF yang masih tersedia di folder sumber akan diperiksa dan dokumen yang belum masuk akan disinkronkan. Catatan gagal lama dihapus; bila gagal lagi akan muncul catatan baru.'
                : 'Riwayat tidak tersedia atau data proses tidak valid untuk retry.',
        );
    }
}
