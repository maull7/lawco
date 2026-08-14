<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sector\StoreSectorRequest;
use App\Http\Requests\Sector\UpdateSectorRequest;
use App\Models\Sector;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        $sectors = Sector::withCount('categories')->orderBy('name')->get();

        return view('sectors.index', compact('sectors'));
    }

    public function create(): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        return view('sectors.create');
    }

    public function store(StoreSectorRequest $request): RedirectResponse
    {
        $sector = Sector::create($request->validated());

        UserActivityLog::log('created', Sector::class, $sector->id, "Menambahkan sektor {$sector->name}");

        return redirect()->route('sectors.index')->with('success', 'Sektor berhasil ditambahkan.');
    }

    public function edit(Sector $sector): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        return view('sectors.edit', compact('sector'));
    }

    public function update(UpdateSectorRequest $request, Sector $sector): RedirectResponse
    {
        $sector->update($request->validated());

        UserActivityLog::log('updated', Sector::class, $sector->id, "Memperbarui sektor {$sector->name}");

        return redirect()->route('sectors.index')->with('success', 'Sektor berhasil diperbarui.');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_categories'), 403);

        $name = $sector->name;
        $sector->categories()->update(['sector_id' => null]);
        $sector->delete();

        UserActivityLog::log('deleted', Sector::class, null, "Menghapus sektor {$name}");

        return redirect()->route('sectors.index')->with('success', 'Sektor berhasil dihapus.');
    }
}
