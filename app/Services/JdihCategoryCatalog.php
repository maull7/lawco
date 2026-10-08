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
        $this->categories = RegulationCategory::query()->get(['id', 'name'])
            ->groupBy(fn (RegulationCategory $category): string => mb_strtolower(trim($category->name)));
    }

    public function resolve(string $name): ?int
    {
        $matches = $this->categories->get(mb_strtolower(trim($name)));

        return $matches?->count() === 1 ? $matches->first()->id : null;
    }

    /** @return list<string> */
    public function uniqueNames(): array
    {
        return $this->categories->filter(fn (Collection $matches): bool => $matches->count() === 1)->keys()->all();
    }
}
