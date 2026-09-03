<?php

namespace App\Repositories;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RegulationRepository
{
    public function paginateWithFilters(array $filters): LengthAwarePaginator
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

        $query = Regulation::with(['type', 'category', 'subCategories', 'documents'])
            ->withCount('documents');

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

            $query->where(function (Builder $q) use ($whereFragment, $whereBindings, $parsed) {
                $q->whereRaw("{$whereFragment}", $whereBindings);
                [$docFragment, $docBindings] = $this->contentMatch('regulation_documents', $parsed['term'], $parsed['exact']);
                $q->orWhereHas('documents', function (Builder $docQuery) use ($docFragment, $docBindings) {
                    $docQuery->whereRaw("{$docFragment}", $docBindings);
                });
            })
                ->selectRaw("regulations.*, CASE WHEN {$relevanceFragment} THEN 1 ELSE 0 END as relevance", $relevanceBindings)
                ->limit(1);
        }

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (! empty($filters['type_id'])) {
            $query->where('regulation_type_id', $filters['type_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['sector_id'])) {
            $query->whereHas('category', function (Builder $q) use ($filters) {
                $q->where('sector_id', $filters['sector_id']);
            });
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

        return $query->paginate(15)->withQueryString();
    }

    public function findByIdWithRelations(int $id): Regulation
    {
        return Regulation::with([
            'type',
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
            'years' => Regulation::select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year'),
            'sectors' => Sector::orderBy('name')->get(),
        ];
    }

    public function getFormOptions(): array
    {
        $sectors = Sector::with(['categories' => fn ($query) => $query
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

        if (! $exact) {
            return ["{$column} LIKE ?", ["%{$term}%"]];
        }

        $escaped = preg_replace('/\s+/u', ' ', trim($term));
        $escaped = preg_quote($escaped, '/');

        // ponytail: sqlite used only by the test suite; MySQL/REGEXP is the production path
        if (DB::connection()->getDriverName() === 'sqlite') {
            return ["(' ' || {$column} || ' ') LIKE ?", ["% {$escaped} %"]];
        }

        return ["{$column} REGEXP ?", ["\\b{$escaped}\\b"]];
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
