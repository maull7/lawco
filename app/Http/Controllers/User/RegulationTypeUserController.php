<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\RegulationType;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegulationTypeUserController extends Controller
{
    public function index(Request $request): View
    {

        $sectorId = $request->integer('sector_id') ?: null;
        $types = RegulationType::withCount('regulations')
            ->when($sectorId, fn ($query) => $query->whereHas('regulations.category', fn ($categoryQuery) => $categoryQuery->where('sector_id', $sectorId)))
            ->with(['regulations.category.sector'])
            ->orderBy('level')
            ->get();
        $sectors = Sector::where('is_active', true)->orderBy('name')->get();

        return view('regulation-types.user.index', compact('types', 'sectors', 'sectorId'));
    }

    public function show(RegulationType $regulationType): View
    {
        $regulationType->load([
            'regulations.category',
            'regulations.documents',
            'regulations.subCategories',
        ]);
        $regulations = $regulationType->regulations()
            ->with([
                'type',
                'documents',
            ])
            ->latest('effective_date')
            ->paginate(10);

        return view('regulation-types.user.show', compact('regulationType', 'regulations'));
    }
}
