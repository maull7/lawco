<?php

namespace App\Repositories;

use App\Models\RegulationCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RegulationCategoryRepository
{
    /** @return Collection<int, RegulationCategory> */
    public function all(?int $sectorId = null): Collection
    {
        return RegulationCategory::with(['sector'])
            ->withCount('files')
            ->when($sectorId, fn (Builder $query): Builder => $query->whereHas('regulations', fn (Builder $regulations): Builder => $regulations->where('sector_id', $sectorId)))
            ->orderBy('name')
            ->get();
    }

    /** @return LengthAwarePaginator<int, RegulationCategory> */
    public function paginate(?int $sectorId = null, string $search = ''): LengthAwarePaginator
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        return RegulationCategory::with('sector:id,name')->withCount(['files', 'regulations'])
            ->when($sectorId, fn (Builder $query): Builder => $query->whereHas('regulations', fn (Builder $regulations): Builder => $regulations->where('sector_id', $sectorId)))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]);
            }))
            ->orderBy('name')->orderBy('id')->paginate(15);
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
