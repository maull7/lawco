<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\RegulationCategory;
use App\Repositories\RegulationCategoryRepository;
use Illuminate\View\View;

class RegulationCategoryUserController extends Controller
{
    public function __construct(
        private readonly RegulationCategoryRepository $categoryRepository
    ) {}

    public function index(\Illuminate\Http\Request $request): \Illuminate\View\View
    {
        $sectorId = $request->integer('sector_id') ?: null;
        $categories = $this->categoryRepository->all($sectorId);
        $sectors = \App\Models\Sector::query()->where('is_active', true)->orderBy('name')->get();

        return view('regulation-categories.user.index', compact('categories', 'sectors', 'sectorId'));
    }

    public function show(RegulationCategory $regulationCategory): View
    {

        $regulationCategory->load([
            'files',
            'subCategories',
        ]);

        $regulations = $regulationCategory->regulations()
            ->with([
                'type',
                'documents',
            ])
            ->latest('effective_date')
            ->paginate(10);

        return view('regulation-categories.user.show', compact(
            'regulationCategory',
            'regulations'
        ));
    }
}
