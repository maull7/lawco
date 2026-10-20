<?php

namespace App\Repositories;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RegulationRepository
{
    public const RESULT_LIMIT = 10000;

    /** Columns needed by the regulation list; skips heavy parsed_text / parse_stats. */
    private const LIST_COLUMNS = [
        'id', 'regulation_number', 'title', 'regulation_type_id', 'sector_id', 'category_id', 'year',
        'file_path', 'parse_status', 'parsed_at', 'parse_progress', 'parse_error',
        'tanggal_tetapkan', 'tanggal_diundangkan', 'created_at', 'updated_at',
    ];

    /** @param array<int, string> $columns */
    public function filteredQuery(array $filters, array $columns = ['regulations.*']): Builder
    {
        $sortField = $filters['sort'] ?? 'year';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = ['regulation_number', 'title', 'year', 'regulation_type_id', 'category_id'];

        if (! in_array($sortField, $allowedSorts)) {
            $sortField = 'year';
        }

        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query = Regulation::query()->select($columns);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('regulation_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['search_content'])) {
            $parsed = $this->parseSearchTerm((string) $filters['search_content']);
            [$whereFragment, $whereBindings] = $this->contentMatch('regulations', $parsed['term'], $parsed['exact']);
            [$relevanceFragment, $relevanceBindings] = $this->contentMatch('regulations', $parsed['term'], $parsed['exact']);

            [$docFragment, $docBindings] = $this->contentMatch('regulation_documents', $parsed['term'], $parsed['exact']);

            // Two IN-subqueries instead of OR + EXISTS so MySQL can use each FULLTEXT index on its own.
            $query->where(fn (Builder $q) => $q
                ->whereIn('regulations.id', fn ($sub) => $sub->select('id')->from('regulations')->whereRaw($whereFragment, $whereBindings))
                ->orWhereIn('regulations.id', fn ($sub) => $sub->select('regulation_id')->from('regulation_documents')->whereRaw($docFragment, $docBindings)))
                ->selectRaw("CASE WHEN {$relevanceFragment} THEN 1 ELSE 0 END as relevance", $relevanceBindings);
        }

        if (! empty($filters['start_date'])) {
            $query->where('regulations.created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('regulations.created_at', '<', Carbon::parse($filters['end_date'])->addDay()->format('Y-m-d'));
        }

        if (! empty($filters['parse_status'])) {
            if ($filters['parse_status'] === 'not_parsed') {
                $query->where(fn (Builder $statusQuery) => $statusQuery
                    ->where('parse_status', 'not_parsed')->orWhereNull('parse_status')->orWhere('parse_status', ''));
            } else {
                $query->where('parse_status', $filters['parse_status']);
            }
        }

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (! empty($filters['type_id'])) {
            $query->where('regulation_type_id', $filters['type_id']);
        }

        if (! empty($filters['category_keyword'])) {
            $keyword = trim($filters['category_keyword']);
            $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                ->where('name', 'like', '%'.$keyword.'%'));
        }
        if (! empty($filters['sector_id'])) {
            $query->where('sector_id', $filters['sector_id']);
        }

        if (! empty($filters['search_content'])) {
            $query->orderByDesc('relevance');
        } elseif ($sortField === 'regulation_type_id') {
            $query->orderBy(
                RegulationType::select('level')
                    ->whereColumn('regulation_types.id', 'regulations.regulation_type_id'),
                $sortDirection
            );
        } elseif ($sortField === 'category_id') {
            $query->orderBy(
                RegulationCategory::select('name')
                    ->whereColumn('regulation_categories.id', 'regulations.category_id'),
                $sortDirection
            );
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $query->orderByDesc('tanggal_diundangkan');

        $query->orderByDesc('regulations.id');

        return $query;
    }

    /**
     * Paginate in two steps: sort/offset over ids only, then hydrate the page rows.
     * Keeps MySQL from filesorting wide rows (parsed_text) across the whole table.
     */
    public function paginateWithFilters(array $filters): LengthAwarePaginator
    {
        $searchesContent = ! empty($filters['search_content']);
        $idQuery = $this->filteredQuery($filters, ['regulations.id']);
        $total = DB::query()->fromSub(
            (clone $idQuery)->toBase()->reorder()->select('regulations.id')->limit(self::RESULT_LIMIT),
            'limited_regulations'
        )->count();
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;
        $items = new Collection;

        if ($offset < $total) {
            $ids = $idQuery->offset($offset)->limit(min($perPage, $total - $offset))->pluck('regulations.id');
            $documentColumns = $searchesContent
                ? ['id', 'regulation_id', 'name', 'document_type', 'parsed_text']
                : ['id', 'regulation_id', 'name', 'document_type'];

            $items = Regulation::query()
                ->when(! $searchesContent, fn (Builder $query) => $query->select(self::LIST_COLUMNS))
                ->with(['type', 'sector', 'category', 'subCategories', 'documents' => fn ($query) => $query->select($documentColumns)])
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (Regulation $regulation) => $ids->search($regulation->id))
                ->values();
        }

        return (new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]))->withQueryString();
    }

    public function findByIdWithRelations(int $id): Regulation
    {
        return Regulation::with([
            'type',
            'sector',
            'category',
            'subCategories',
            'relatedRegulations.type',
            'documents',
            'relatedReferences',
            'aiResults',
        ])->findOrFail($id);
    }

    public function search(string $query, ?int $excludeId = null): Collection
    {
        return Regulation::where(function (Builder $q) use ($query) {
            $q->where('regulation_number', 'like', "%{$query}%")
                ->orWhere('title', 'like', "%{$query}%")
                ->orWhere('year', 'like', "%{$query}%");
        })
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->with('type')
            ->orderByDesc('year')
            ->limit(20)
            ->get();
    }

    public function getFilterOptions(): array
    {
        return [
            'types' => RegulationType::orderBy('level')->get(),
            'categories' => RegulationCategory::orderBy('name')->get(),
            'years' => collect(Cache::remember('regulations.filter_years', 600, fn () => Regulation::select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->all())),
            'sectors' => Sector::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    public function getFormOptions(): array
    {
        $sectors = Sector::where('is_active', true)->with(['categories' => fn ($query) => $query
            ->with(['subCategories' => fn ($subCategoryQuery) => $subCategoryQuery
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('name')])
            ->orderBy('name')
            ->get();

        return [
            'types' => RegulationType::where('is_active', true)->orderBy('level')->get(),
            'sectors' => $sectors,
            'categories' => $sectors->flatMap(fn ($sector) => $sector->categories),
        ];
    }

    /**
     * @return array{term: string, exact: bool}
     */
    private function parseSearchTerm(string $raw): array
    {
        if (str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
            $inner = trim(mb_substr($raw, 1, -1));

            if ($inner !== '') {
                return ['term' => $inner, 'exact' => true];
            }
        }

        return ['term' => $raw, 'exact' => false];
    }

    /**
     * Build a prepared SQL fragment that matches a term inside a parsed_text column.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function contentMatch(string $table, string $term, bool $exact): array
    {
        $column = "{$table}.parsed_text";
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($exact) {
            $escaped = preg_quote(preg_replace('/\s+/u', ' ', trim($term)), '/');

            // ponytail: sqlite used only by the test suite; MySQL/REGEXP is the production path
            [$fragment, $bindings] = $isSqlite
                ? ["(' ' || {$column} || ' ') LIKE ?", ["% {$escaped} %"]]
                : ["{$column} REGEXP ?", ["\\b{$escaped}\\b"]];
        } else {
            [$fragment, $bindings] = ["{$column} LIKE ?", ["%{$term}%"]];
        }

        $booleanQuery = $isSqlite ? '' : $this->fulltextBooleanQuery($term);

        if ($booleanQuery === '') {
            return [$fragment, $bindings];
        }

        // FULLTEXT narrows rows through the index; LIKE/REGEXP then keeps the original match semantics.
        return ["(MATCH({$column}) AGAINST(? IN BOOLEAN MODE) AND {$fragment})", [$booleanQuery, ...$bindings]];
    }

    /**
     * Turn a search term into "+word* +word*" for MySQL boolean FULLTEXT search.
     * Words shorter than innodb_ft_min_token_size (3) are not indexed, so they are left to LIKE/REGEXP.
     */
    private function fulltextBooleanQuery(string $term): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, fn (string $word) => mb_strlen($word) >= 3);

        return implode(' ', array_map(fn (string $word) => "+{$word}*", $words));
    }

    public function buildSnippet(?string $text, string $keyword, int $length = 200): ?string
    {
        if (! $text || ! $keyword) {
            return null;
        }

        $parsed = $this->parseSearchTerm($keyword);

        if ($parsed['exact']) {
            $escaped = preg_quote(preg_replace('/\s+/u', ' ', trim($parsed['term'])), '/');
            preg_match("/\b{$escaped}\b/iu", $text, $matches, PREG_OFFSET_CAPTURE);
            $pos = $matches[0][1] ?? false;
        } else {
            $pos = mb_stripos($text, $keyword);
        }

        if ($pos === false) {
            return mb_substr($text, 0, $length).'...';
        }

        $start = max(0, $pos - 100);
        $snippet = mb_substr($text, $start, $length);

        return ($start > 0 ? '...' : '').$snippet.'...';
    }
}
