<?php

namespace App\Services;

use App\Console\Commands\SyncRegulationsFromJdih;
use App\Models\JdihDocumentReview;
use App\Models\JdihTarget;
use App\Models\RegulationType;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class JdihReviewDocuments
{
    public function __construct(private SyncRegulationsFromJdih $sync) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<string, JdihTarget>  $targets
     * @return array{documents: LengthAwarePaginator, types: array<string, array{name: string, count: int}>, categories: array<string, array{name: string, sector: string, sector_id: ?int, count: int}>, warning: ?string}
     */
    public function listing(array $filters, Collection $targets, int $page, string $routeName = 'scraping-failures.index'): array
    {
        $items = [];
        $types = [];
        $categories = [];
        $total = 0;
        $warning = null;
        $masterNames = RegulationType::query()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all();
        $categoryCatalog = new JdihCategoryCatalog;
        $reviewTab = ($filters['tab'] ?? 'masters') === 'needs_review';
        $allReviews = $routeName === 'jdih-document-reviews.index';
        $fileExists = [];
        $root = rtrim((string) config('database.connections.jdih.scraper_root', ''), '/\\');
        try {
            $query = DB::connection('jdih')->table('regulations')
                ->whereNotNull('local_path')->where('local_path', '!=', '');
            if ($reviewTab && ! $allReviews) {
                $query->where(fn (Builder $query) => $query->whereNull('regulation_type')
                    ->orWhereRaw('LOWER(TRIM(regulation_type)) IN (?, ?)', ['', 'needs_review']));
            } else {
                $query->where(fn (Builder $query) => $query->whereNotIn('regulation_type', $this->sync->mappedTypeSlugs())
                    ->orWhereNull('regulation_type')->orWhere(fn (Builder $query) => $query->where('category', '!=', '')
                    ->whereNotIn(DB::raw('LOWER(TRIM(category))'), $categoryCatalog->names())));
            }
            if (! empty($filters['sector_id'])) {
                $query->whereIn('source', $targets->where('sector_id', (int) $filters['sector_id'])->keys()->all());
            }
            $keyword = trim($filters['q'] ?? '');
            if ($keyword !== '') {
                $sources = $targets->filter(fn (JdihTarget $target): bool => str_contains(mb_strtolower($target->name.' '.$target->sector?->name), mb_strtolower($keyword)))->keys()->all();
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';
                $query->where(function (Builder $query) use ($pattern, $sources): void {
                    $query->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("regulation_type LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("document_id LIKE ? ESCAPE '!'", [$pattern]);
                    if ($sources !== []) {
                        $query->orWhereIn('source', $sources);
                    }
                });
            }
            $query->select(['source', 'document_id', 'title', 'regulation_type', 'local_path', 'category'])
                ->orderBy('source')->orderBy('document_id')->chunk(500, function (Collection $rows) use (&$items, &$types, &$categories, &$total, $targets, $masterNames, $categoryCatalog, $root, $page, $reviewTab, $allReviews, &$fileExists): void {
                    $manualCategories = JdihDocumentReview::with('category')->whereIn('source', $rows->pluck('source')->unique())
                        ->whereIn('document_id', $rows->pluck('document_id')->unique())->get()
                        ->keyBy(fn (JdihDocumentReview $choice): string => $choice->source.':'.$choice->document_id);
                    $classified = [];
                    $candidates = $rows->filter(function (object $row) use ($categoryCatalog, $reviewTab, $allReviews, $manualCategories, $targets, &$classified): bool {
                        $slug = trim((string) $row->regulation_type);
                        $typeMissing = ! in_array($slug, $this->sync->mappedTypeSlugs(), true);
                        $manualCategory = $manualCategories->get($row->source.':'.$row->document_id);
                        $categoryMissing = $manualCategory?->category_id !== null
                            ? $manualCategory->category === null
                            : (trim((string) $row->category) !== '' && $categoryCatalog->resolve((string) $row->category,
                                (int) ($targets->get($row->source)?->sector_id ?: config('database.connections.jdih.default_sector_id', 1))) === null);
                        $unknown = $slug === '' || mb_strtolower($slug) === 'needs_review';
                        if ((! $typeMissing && ! $categoryMissing) || (! $allReviews && ($unknown && $typeMissing) !== $reviewTab)) {
                            return false;
                        }
                        $classified[$row->source.':'.$row->document_id] = compact('slug', 'typeMissing', 'categoryMissing', 'unknown');

                        return true;
                    });
                    if ($candidates->isEmpty()) {
                        return;
                    }
                    $synced = DB::table('jdih_sync_log')->where(function (Builder $query) use ($candidates): void {
                        foreach ($candidates->groupBy('source') as $source => $documents) {
                            $query->orWhere(fn (Builder $query) => $query->where('jdih_source', $source)->whereIn('jdih_document_id', $documents->pluck('document_id')->all()));
                        }
                    })->get(['jdih_source', 'jdih_document_id'])->keyBy(fn (object $row): string => $row->jdih_source.':'.$row->jdih_document_id);
                    foreach ($candidates as $row) {
                        if ($synced->has($row->source.':'.$row->document_id)) {
                            continue;
                        }
                        $raw = trim((string) $row->local_path);
                        $path = str_starts_with($raw, '/') ? $raw : ($root !== '' ? $root.'/'.$raw : '');
                        $fileExists[$path] ??= $path !== '' && is_file($path);
                        if (! $fileExists[$path]) {
                            continue;
                        }
                        $classification = $classified[$row->source.':'.$row->document_id];
                        $slug = $classification['slug'];
                        $typeMissing = $classification['typeMissing'];
                        $categoryMissing = $classification['categoryMissing'];
                        $unknown = $classification['unknown'];
                        $target = $targets->get($row->source);
                        $name = match ($slug) {
                            'surat_menteri' => 'Surat Menteri',
                            'perjanjian_kerjasama' => 'Perjanjian Kerja Sama',
                            default => $unknown ? 'Jenis belum ditentukan di JDIH' : Str::headline($slug),
                        };
                        if ($typeMissing) {
                            $types[$slug] ??= ['name' => $name, 'count' => 0];
                            $types[$slug]['count']++;
                        }
                        if ($categoryMissing) {
                            $key = ($target?->sector_id ?: config('database.connections.jdih.default_sector_id', 1)).':'.trim($row->category);
                            $categories[$key] ??= ['name' => trim($row->category), 'sector' => $target?->sector?->name ?? 'Sektor default', 'sector_id' => $target?->sector_id ?: (int) config('database.connections.jdih.default_sector_id', 1), 'count' => 0];
                            $categories[$key]['count']++;
                        }
                        $total++;
                        if ($total <= ($page - 1) * 20 || $total > $page * 20) {
                            continue;
                        }
                        $target = $targets->get($row->source);
                        $items[] = [
                            'source' => $row->source, 'source_name' => $target?->name ?? $row->source,
                            'sector' => $target?->sector?->name ?? 'Belum dipetakan',
                            'source_url' => $target && in_array(parse_url((string) $target->target_url, PHP_URL_SCHEME), ['http', 'https'], true) ? $target->target_url : null,
                            'id' => $row->document_id, 'title' => $row->title,
                            'filename' => basename(str_replace('\\', '/', $row->local_path)),
                            'category' => trim($row->category) ?: 'Tidak diisi di JDIH',
                            'slug' => $slug ?: 'Kosong', 'suggestion' => $name,
                            'reason' => ! $typeMissing ? 'Kategori JDIH belum dapat ditentukan dari master Lawco.' : ($unknown ? 'Jenis regulasi di JDIH belum ditentukan; pilih jenis berdasarkan isi PDF.'
                                : 'Jenis dari JDIH belum dipetakan oleh sinkronisasi Lawco.'),
                            'action' => ($categoryMissing ? 'Pilih kategori '.trim($row->category).' di menu pemeriksaan atau tambahkan master kategori, lalu retry. ' : '').(! $typeMissing ? 'Jenis regulasi sudah dikenali.' : ($unknown ? 'Periksa PDF dan pilih jenis yang sesuai saat menambahkan regulasi manual.'
                                : (in_array(mb_strtolower($name), $masterNames, true)
                                    ? 'Jenis ini sudah ada di Lawco. Gunakan untuk input manual; retry otomatis tetap membutuhkan pemetaan kode.'
                                    : 'Tambahkan jenis '.$name.' di Jenis Regulasi, lalu input regulasi manual. Retry otomatis tetap membutuhkan pemetaan kode.'))),
                        ];
                    }
                });
        } catch (Throwable $exception) {
            report($exception);
            $warning = 'Daftar dokumen perlu pemeriksaan belum dapat dibaca dari database JDIH.';
            $items = [];
            $types = [];
            $categories = [];
            $total = 0;
        }
        $documents = new LengthAwarePaginator($items, $total, 20, $page, ['path' => route($routeName), 'pageName' => 'review_page']);
        $documents->withQueryString();

        return compact('documents', 'types', 'categories', 'warning');
    }
}
