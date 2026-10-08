<?php

namespace App\Services;

use App\Models\RegulationCategory;
use Illuminate\Support\Collection;

class JdihCategoryCatalog
{
    /** @var Collection<string, Collection<int, RegulationCategory>> */
    private Collection $categories;

    public function __construct()
    {
        $this->categories = RegulationCategory::query()->orderBy('id')->get(['id', 'name', 'sector_id'])
            ->groupBy(fn (RegulationCategory $category): string => mb_strtolower(trim($category->name)));
    }

    public function resolve(string $name, ?int $sectorId = null): ?int
    {
        $matches = $this->categories->get(mb_strtolower(trim($name)));

        $category = $sectorId !== null ? $matches?->firstWhere('sector_id', $sectorId) : null;

        return ($category ?? $matches?->first())?->id;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->categories->keys()->all();
    }
}
