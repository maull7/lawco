<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegulationType\StoreRegulationTypeRequest;
use App\Http\Requests\RegulationType\UpdateRegulationTypeRequest;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegulationTypeController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_types'), 403);

        $sectorId = $request->integer('sector_id') ?: null;
        $types = RegulationType::withCount('regulations')
            ->when($sectorId, fn ($query) => $query->whereHas('regulations.category', fn ($categoryQuery) => $categoryQuery->where('sector_id', $sectorId)))
            ->with(['regulations.category.sector'])
            ->orderBy('level')
            ->get();
        $sectors = Sector::where('is_active', true)->orderBy('name')->get();

        return view('regulation-types.index', compact('types', 'sectors', 'sectorId'));
    }

    public function create(): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_types'), 403);

        return view('regulation-types.create');
    }

    public function store(StoreRegulationTypeRequest $request): RedirectResponse
    {
        $type = RegulationType::create($request->validated());

        UserActivityLog::log('created', RegulationType::class, $type->id, "Menambahkan jenis regulasi {$type->name}");

        return redirect()->route('regulation-types.index')
            ->with('success', 'Jenis regulasi berhasil ditambahkan.');
    }

    public function edit(RegulationType $regulationType): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_types'), 403);

        return view('regulation-types.edit', compact('regulationType'));
    }

    public function update(UpdateRegulationTypeRequest $request, RegulationType $regulationType): RedirectResponse
    {
        $regulationType->update($request->validated());

        UserActivityLog::log('updated', RegulationType::class, $regulationType->id, "Memperbarui jenis regulasi {$regulationType->name}");

        return redirect()->route('regulation-types.index')
            ->with('success', 'Jenis regulasi berhasil diperbarui.');
    }

    public function destroy(RegulationType $regulationType): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_types'), 403);

        if ($regulationType->regulations()->exists()) {
            return redirect()->route('regulation-types.index')
                ->with('error', 'Tidak dapat menghapus jenis regulasi yang masih digunakan oleh regulasi.');
        }

        $name = $regulationType->name;
        $regulationType->delete();

        UserActivityLog::log('deleted', RegulationType::class, null, "Menghapus jenis regulasi {$name}");

        return redirect()->route('regulation-types.index')
            ->with('success', 'Jenis regulasi berhasil dihapus.');
    }

    public function toggle(RegulationType $regulationType): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_types'), 403);

        $regulationType->is_active = ! $regulationType->is_active;
        $regulationType->save();

        $action = $regulationType->is_active ? 'activated' : 'deactivated';
        UserActivityLog::log($action, RegulationType::class, $regulationType->id, "Mengubah status jenis regulasi {$regulationType->name} menjadi ".($regulationType->is_active ? 'aktif' : 'nonaktif'));

        return redirect()->route('regulation-types.index')
            ->with('success', 'Status jenis regulasi berhasil diperbarui.');
    }

    public function show(RegulationType $regulationType): View
    {
        abort_if(
            auth()->user()->isSubAdmin() &&
                ! auth()->user()->hasPermission('manage_types'),
            403
        );

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

        return view('regulation-types.show', compact('regulationType', 'regulations'));
    }
}
