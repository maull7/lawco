<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sector\StoreSectorRequest;
use App\Http\Requests\Sector\UpdateSectorRequest;
use App\Models\Sector;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        $search = trim((string) $request->string('search'));

        $sectors = Sector::with(['categories' => fn ($query) => $query
            ->with(['subCategories' => fn ($subCategoryQuery) => $subCategoryQuery
                ->where('is_active', true)
                ->withExists('regulations')
                ->orderBy('name')])
            ->withExists('regulations')
            ->orderBy('name')])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->withCount(['categories', 'regulations', 'regulations as uncategorized_regulations_count' => fn ($query) => $query->whereNull('category_id')])
            ->orderBy('name')
            ->get();

        return view('sectors.index', compact('sectors', 'search'));
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

    public function toggle(Sector $sector): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_categories'), 403);

        $sector->update(['is_active' => ! $sector->is_active]);

        UserActivityLog::log('toggled', Sector::class, $sector->id, "Mengubah status sektor {$sector->name} menjadi ".($sector->is_active ? 'aktif' : 'nonaktif'));

        return redirect()->route('sectors.index')->with('success', 'Status sektor berhasil diperbarui.');
    }

    public function toggleVisibility(Sector $sector): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_categories'), 403);

        $sector->update(['is_public' => ! $sector->is_public]);

        UserActivityLog::log('updated', Sector::class, $sector->id, "Mengubah visibilitas sektor {$sector->name} menjadi ".($sector->is_public ? 'publik' : 'non publik'));

        return redirect()->route('sectors.index')->with('success', 'Visibilitas sektor berhasil diperbarui.');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_categories'), 403);

        $name = $sector->name;
        $sector->regulations()->update(['sector_id' => null]);
        $sector->categories()->update(['sector_id' => null]);
        $sector->delete();

        UserActivityLog::log('deleted', Sector::class, null, "Menghapus sektor {$name}");

        return redirect()->route('sectors.index')->with('success', 'Sektor berhasil dihapus.');
    }
}
