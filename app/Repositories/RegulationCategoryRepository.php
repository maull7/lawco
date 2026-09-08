<?php

namespace App\Repositories;

use App\Models\RegulationCategory;
use Illuminate\Database\Eloquent\Collection;

class RegulationCategoryRepository
{
    /** @return Collection<int, RegulationCategory> */
    public function all(?int $sectorId = null): Collection
    {
        return RegulationCategory::with(['sector'])
            ->withCount('files')
            ->when($sectorId, fn ($query) => $query->where('sector_id', $sectorId))
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): RegulationCategory
    {
        return RegulationCategory::with('files')->findOrFail($id);
    }

    /** @return Collection<int, RegulationCategory> */
    public function allWithFiles(): Collection
    {
        return RegulationCategory::with('files')->get();
    }

    /** @return Collection<int, RegulationCategory> */
    public function allWithRegulations(): Collection
    {
        return RegulationCategory::with('regulations.type')->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): RegulationCategory
    {
        return RegulationCategory::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(RegulationCategory $category, array $data): RegulationCategory
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(RegulationCategory $category): bool
    {
        return $category->delete();
    }
}
